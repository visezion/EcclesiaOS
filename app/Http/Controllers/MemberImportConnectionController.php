<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Church;
use App\Models\MemberImport;
use App\Models\MemberImportConnection;
use App\Services\ActivityLogger;
use App\Services\MemberImport\MemberImportDatabaseReader;
use App\Services\MemberImport\MemberImportMapper;
use App\Services\MemberImport\MemberImportStager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemberImportConnectionController extends Controller
{
    public function __construct(
        private readonly MemberImportDatabaseReader $reader,
        private readonly MemberImportMapper $mapper,
        private readonly MemberImportStager $stager,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeImport($request);

        return $this->view($request);
    }

    public function show(Request $request, MemberImportConnection $connection): View
    {
        $this->authorizeConnection($request, $connection);
        $tables = [];
        $connectionError = null;
        try {
            $tables = $this->reader->tables($connection);
        } catch (Throwable $exception) {
            $connectionError = $exception->getMessage();
        }

        return $this->view($request, $connection, $tables, $connectionError);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeImport($request);
        $churchId = $this->churchId($request);
        $drivers = array_keys(MemberImportDatabaseReader::DRIVERS);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('member_import_connections')->where(fn ($query) => $query->where('church_id', $churchId))],
            'driver' => ['required', Rule::in($drivers)],
            'host' => [Rule::requiredIf(fn () => $request->input('driver') !== 'sqlite'), 'nullable', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database_name' => [Rule::requiredIf(fn () => $request->input('driver') !== 'sqlite'), 'nullable', 'string', 'max:255'],
            'username' => [Rule::requiredIf(fn () => $request->input('driver') !== 'sqlite'), 'nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:1000'],
            'sqlite_file' => [Rule::requiredIf(fn () => $request->input('driver') === 'sqlite'), 'nullable', 'file', 'max:204800'],
            'schema' => ['nullable', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/', 'max:63'],
            'sslmode' => ['nullable', Rule::in(['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'])],
            'encrypt' => ['nullable', 'boolean'],
            'trust_server_certificate' => ['nullable', 'boolean'],
        ]);
        if (! data_get($this->reader->capabilities(), $validated['driver'].'.available')) {
            throw ValidationException::withMessages(['driver' => MemberImportDatabaseReader::DRIVERS[$validated['driver']]['label'].' requires its PDO PHP extension to be enabled on this server.']);
        }
        $databaseName = (string) ($validated['database_name'] ?? '');
        if ($validated['driver'] === 'sqlite') {
            $databaseName = $validated['sqlite_file']->store('member-imports/'.$churchId.'/databases', 'local');
        }
        $connection = MemberImportConnection::query()->create([
            'church_id' => $churchId,
            'created_by' => $request->user()->id,
            'name' => trim($validated['name']),
            'driver' => $validated['driver'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? MemberImportDatabaseReader::DRIVERS[$validated['driver']]['default_port'],
            'database_name' => $databaseName,
            'username' => $validated['username'] ?? null,
            'password_encrypted' => filled($validated['password'] ?? null) ? Crypt::encryptString($validated['password']) : null,
            'options' => [
                'schema' => $validated['schema'] ?? 'public',
                'sslmode' => $validated['sslmode'] ?? 'prefer',
                'encrypt' => $request->boolean('encrypt', true),
                'trust_server_certificate' => $request->boolean('trust_server_certificate'),
            ],
            'is_active' => true,
        ]);

        return redirect()->route('member-import-connections.show', $connection)->with('status', 'Read-only data source saved. Select Test connection before importing.');
    }

    public function test(Request $request, MemberImportConnection $connection): RedirectResponse
    {
        $this->authorizeConnection($request, $connection);
        try {
            $result = $this->reader->test($connection);
            $connection->update(['last_tested_at' => now(), 'last_test_status' => 'success', 'last_error' => null]);

            return back()->with('status', 'Connection successful: '.$result['tables'].' tables found in '.$result['latency_ms'].' ms.');
        } catch (Throwable $exception) {
            $connection->update(['last_tested_at' => now(), 'last_test_status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 2000)]);

            return back()->withErrors(['connection' => 'Connection failed: '.$exception->getMessage()]);
        }
    }

    public function stage(Request $request, MemberImportConnection $connection, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeConnection($request, $connection);
        $validated = $request->validate([
            'table' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'],
            'default_campus_id' => ['required', Rule::exists('campuses', 'id')->where(fn ($query) => $query->where('church_id', $connection->church_id)->where('status', 'active'))],
        ]);
        try {
            $parsed = $this->reader->read($connection, $validated['table']);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['table' => 'The table could not be read: '.$exception->getMessage()]);
        }
        $mapping = $this->mapper->autoMap($parsed['headers']);
        $import = MemberImport::query()->create([
            'reference' => 'MIM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'church_id' => $connection->church_id,
            'created_by' => $request->user()->id,
            'connection_id' => $connection->id,
            'name' => filled($validated['name'] ?? null) ? trim($validated['name']) : $connection->name.' · '.$validated['table'],
            'source_type' => 'database',
            'source_table' => $validated['table'],
            'status' => 'analyzing',
            'source_options' => [
                'source_name' => $connection->name,
                'driver' => $connection->driver,
                'headers' => $parsed['headers'],
            ],
            'mapping' => $mapping,
            'options' => ['default_campus_id' => (int) $validated['default_campus_id'], 'duplicate_strategy' => 'skip', 'create_families' => true],
            'total_rows' => count($parsed['rows']),
        ]);
        $this->stager->stage($import, $parsed['rows'], $mapping);
        $logger->log('Members', 'member_database_import_staged', $import->reference.' was staged from a read-only database.', $import, [
            'resource' => 'Member Import', 'connection' => $connection->name, 'table' => $validated['table'], 'rows' => count($parsed['rows']), 'status' => 'success', 'risk' => 'medium',
        ], $request);

        return redirect()->route('member-imports.show', $import)->with('status', 'Database rows copied into a safe review area. The source database was not changed.');
    }

    public function destroy(Request $request, MemberImportConnection $connection): RedirectResponse
    {
        $this->authorizeConnection($request, $connection);
        if ($connection->driver === 'sqlite') {
            Storage::disk('local')->delete($connection->database_name);
        }
        $connection->delete();

        return redirect()->route('member-import-connections.index')->with('status', 'Data source removed.');
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function view(Request $request, ?MemberImportConnection $selected = null, array $tables = [], ?string $connectionError = null): View
    {
        $churchId = $this->churchId($request);

        return view('member-import-connections.index', [
            'connections' => MemberImportConnection::query()->where('church_id', $churchId)->latest()->get(),
            'selected' => $selected,
            'tables' => $tables,
            'connectionError' => $connectionError,
            'drivers' => $this->reader->capabilities(),
            'campuses' => Campus::query()->where('church_id', $churchId)->where('status', 'active')->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Import Center', 'url' => route('member-imports.index')],
                ['label' => 'Database Sources', 'url' => null],
            ],
        ]);
    }

    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage members'), 403);
    }

    private function authorizeConnection(Request $request, MemberImportConnection $connection): void
    {
        $this->authorizeImport($request);
        abort_unless($request->user()->isSuperAdministrator() || $connection->church_id === $request->user()->church_id, 404);
    }

    private function churchId(Request $request): int
    {
        return (int) ($request->user()->church_id ?: Church::query()->value('id'));
    }
}
