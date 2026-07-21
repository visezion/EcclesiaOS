<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CampusManagementController extends Controller
{
    public function __invoke(): View
    {
        $campuses = Campus::query()
            ->with(['church', 'users.roles'])
            ->withCount(['users', 'members'])
            ->orderBy('name')
            ->get();
        $users = User::query()->with(['church', 'campus', 'roles'])->orderBy('name')->get();
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        return view('admin.campuses', [
            'churches' => Church::query()->withCount('campuses')->orderBy('name')->get(),
            'campuses' => $campuses,
            'users' => $users,
            'roles' => $roles,
            'stats' => [
                'churches' => Church::query()->count(),
                'campuses' => $campuses->count(),
                'assigned' => $users->whereNotNull('campus_id')->count(),
                'active' => $users->where('status', 'active')->whereNotNull('campus_id')->count(),
                'pending' => $users->where('status', 'inactive')->count(),
                'unassigned' => $users->filter(fn (User $user): bool => $user->church_id === null || $user->campus_id === null)->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Churches & Campuses', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'church_name' => ['required_without:church_id', 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $church = filled($validated['church_id'] ?? null)
            ? Church::query()->findOrFail($validated['church_id'])
            : Church::query()->create([
                'name' => $validated['church_name'],
                'slug' => $this->uniqueSlug(Church::class, $validated['church_name']),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
            ]);

        Campus::query()->create([
            'church_id' => $church->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug(Campus::class, $validated['name'], ['church_id' => $church->id]),
            'type' => $validated['type'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'address' => $validated['address'],
            'capacity' => $validated['capacity'] ?? null,
            'status' => $validated['status'],
            'map_x' => random_int(28, 72),
            'map_y' => random_int(42, 72),
        ]);

        return back()->with('status', 'Campus created.');
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($validated['import_file']->getRealPath(), 'r');
        $imported = 0;

        if ($handle === false) {
            return back()->withErrors(['import_file' => 'The import file could not be opened.']);
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6 || strcasecmp((string) $row[0], 'church') === 0) {
                continue;
            }

            [$churchName, $campusName, $type, $city, $country, $status] = array_pad($row, 7, null);

            if (! filled($churchName) || ! filled($campusName)) {
                continue;
            }

            $church = Church::query()->firstOrCreate(
                ['slug' => Str::slug((string) $churchName)],
                [
                    'name' => (string) $churchName,
                    'timezone' => config('church.timezone'),
                    'currency' => config('church.currency'),
                ],
            );

            Campus::query()->updateOrCreate(
                ['church_id' => $church->id, 'slug' => Str::slug((string) $campusName)],
                [
                    'name' => (string) $campusName,
                    'type' => filled($type) ? (string) $type : 'Regional Campus',
                    'city' => filled($city) ? (string) $city : 'Dallas',
                    'country' => filled($country) ? (string) $country : 'USA',
                    'address' => filled($city) ? (string) $city.', TX' : 'Dallas, TX',
                    'status' => strtolower((string) ($status ?: 'active')),
                    'map_x' => random_int(28, 72),
                    'map_y' => random_int(42, 72),
                ],
            );

            $imported++;
        }

        fclose($handle);

        return back()->with('status', number_format($imported).' campuses imported.');
    }

    /**
     * @param  class-string<Church|Campus>  $model
     * @param  array<string, mixed>  $scope
     */
    private function uniqueSlug(string $model, string $name, array $scope = []): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $index = 2;

        while ($model::query()->where($scope)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
