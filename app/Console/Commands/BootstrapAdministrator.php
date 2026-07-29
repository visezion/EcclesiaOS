<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class BootstrapAdministrator extends Command
{
    protected $signature = 'app:bootstrap-admin
        {--name= : Administrator name; defaults to BOOTSTRAP_ADMIN_NAME}
        {--email= : Administrator email; defaults to BOOTSTRAP_ADMIN_EMAIL}
        {--password= : Administrator password; prefer BOOTSTRAP_ADMIN_PASSWORD}
        {--check : Return success only when a Super Administrator exists}
        {--force : Replace the credentials of an existing administrator}';

    protected $description = 'Create the first church and Super Administrator without demo data';

    public function handle(): int
    {
        $existingAdministrator = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))
            ->first();

        if ($this->option('check')) {
            if ($existingAdministrator) {
                $this->info("Super Administrator {$existingAdministrator->email} exists.");

                return self::SUCCESS;
            }

            $this->warn('No Super Administrator exists.');

            return self::FAILURE;
        }

        if ($existingAdministrator && ! $this->option('force')) {
            $this->info("A Super Administrator already exists ({$existingAdministrator->email}).");

            return self::SUCCESS;
        }

        $name = trim((string) ($this->option('name') ?: getenv('BOOTSTRAP_ADMIN_NAME')));
        $email = trim((string) ($this->option('email') ?: getenv('BOOTSTRAP_ADMIN_EMAIL')));
        $password = (string) ($this->option('password') ?: getenv('BOOTSTRAP_ADMIN_PASSWORD'));

        if ($this->input->isInteractive()) {
            $name = $name ?: (string) $this->ask('Administrator name', 'Church Administrator');
            $email = $email ?: (string) $this->ask('Administrator email');
            $password = $password ?: (string) $this->secret('Administrator password');
        }

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($existingAdministrator, $name, $email, $password): void {
            $church = Church::query()->firstOrCreate(
                ['slug' => Str::slug((string) config('church.name'))],
                [
                    'name' => config('church.name'),
                    'timezone' => config('church.timezone'),
                    'currency' => config('church.currency'),
                    'email' => config('church.contact_email'),
                    'phone' => config('church.contact_phone'),
                    'address' => config('church.address'),
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

            $roles = collect(config('access.roles'))->mapWithKeys(
                function (array $rolePermissions, string $roleName) use ($permissions): array {
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
                },
            );

            $administrator = $existingAdministrator ?: new User;
            $administrator->fill([
                'church_id' => $church->id,
                'campus_id' => $campus->id,
                'name' => $name,
                'title' => 'Church Administrator',
                'email' => $email,
                'status' => 'active',
                'password' => $password,
                'password_changed_at' => now(),
            ]);
            $administrator->forceFill(['email_verified_at' => now()]);
            $administrator->save();
            $administrator->roles()->syncWithoutDetaching([$roles['Super Administrator']->id]);
        });

        $this->info("Super Administrator {$email} is ready.");

        return self::SUCCESS;
    }
}
