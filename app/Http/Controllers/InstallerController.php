<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class InstallerController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->installationComplete()) {
            return redirect()->route('login');
        }

        $branding = Branding::current();

        return view('installer.create', [
            'branding' => $branding,
            'settings' => $branding->settings,
            'timezones' => [
                'UTC' => 'UTC',
                'Africa/Lagos' => 'Africa/Lagos',
                'Europe/London' => 'Europe/London',
                'America/New_York' => 'America/New_York',
                'Asia/Nicosia' => 'Asia/Nicosia',
            ],
            'currencies' => ['USD', 'EUR', 'GBP', 'NGN', 'GHS', 'KES'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->installationComplete()) {
            return redirect()->route('login');
        }

        $data = Validator::make($request->all(), [
            'church_name' => ['required', 'string', 'max:255'],
            'church_address' => ['nullable', 'string', 'max:255'],
            'church_timezone' => ['required', 'string', 'max:255'],
            'church_currency' => ['required', 'string', 'size:3'],
            'church_email' => ['required', 'email:rfc', 'max:255'],
            'church_phone' => ['nullable', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'admin_password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ])->validate();

        $installationLock = Cache::lock('ecclesiaos:installer:bootstrap', 30);
        if (! $installationLock->get()) {
            return back()->withInput($request->except('admin_password'))->withErrors([
                'admin_email' => 'Another installation is already in progress. Wait a moment and try again.',
            ]);
        }

        try {
            if ($this->installationComplete()) {
                return redirect()->route('login');
            }

            DB::transaction(function () use ($data): void {
                $church = Church::query()->updateOrCreate(
                    ['slug' => Str::slug($data['church_name'])],
                    [
                        'name' => $data['church_name'],
                        'timezone' => $data['church_timezone'],
                        'currency' => strtoupper($data['church_currency']),
                        'email' => $data['church_email'],
                        'phone' => $data['church_phone'] ?? null,
                        'address' => $data['church_address'] ?? null,
                    ],
                );

                $campus = Campus::query()->firstOrCreate(
                    ['church_id' => $church->id, 'slug' => 'headquarters'],
                    [
                        'name' => 'Headquarters',
                        'type' => 'Main Campus',
                        'address' => $church->address,
                        'status' => 'active',
                    ],
                );

                $permissions = collect(config('access.permissions'))
                    ->unique()
                    ->mapWithKeys(fn (string $permission): array => [
                        $permission => Permission::query()->updateOrCreate(
                            ['slug' => Str::slug($permission)],
                            ['name' => $permission, 'description' => 'Allows user to '.$permission],
                        ),
                    ]);

                $roles = collect(config('access.roles'))->mapWithKeys(function (array $rolePermissions, string $roleName) use ($permissions): array {
                    $role = Role::query()->updateOrCreate(
                        ['slug' => Str::slug($roleName)],
                        ['name' => $roleName, 'description' => $roleName.' application role'],
                    );

                    $role->permissions()->sync(
                        $rolePermissions === ['*']
                            ? $permissions->pluck('id')->all()
                            : $permissions->only($rolePermissions)->pluck('id')->all(),
                    );

                    return [$roleName => $role];
                });

                $administrator = User::query()->updateOrCreate(
                    ['email' => $data['admin_email']],
                    [
                        'church_id' => $church->id,
                        'campus_id' => $campus->id,
                        'name' => $data['admin_name'],
                        'title' => 'Church Administrator',
                        'status' => 'active',
                        'password' => $data['admin_password'],
                        'password_changed_at' => now(),
                        'email_verified_at' => now(),
                    ],
                );

                $administrator->roles()->syncWithoutDetaching([$roles['Super Administrator']->id]);
            });
        } finally {
            $installationLock->release();
        }

        return redirect()->route('login')->with('status', 'Installation complete. Sign in with the administrator account you just created.');
    }

    private function installationComplete(): bool
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))
            ->exists();
    }
}
