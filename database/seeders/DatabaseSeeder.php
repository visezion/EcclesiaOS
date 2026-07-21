<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $church = Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => config('church.address'),
                'settings' => ['branding' => ['primary' => '#6d4aff']],
            ],
        );

        $campus = Campus::query()->firstOrCreate(
            ['church_id' => $church->id, 'slug' => 'headquarters'],
            ['name' => 'Headquarters', 'city' => 'Lagos', 'country' => 'Nigeria', 'status' => 'active'],
        );

        $permissions = collect(config('navigation'))
            ->pluck('permission')
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $permission): array => [
                $permission => Permission::query()->firstOrCreate(
                    ['slug' => Str::slug($permission)],
                    ['name' => $permission, 'description' => 'Allows user to '.$permission],
                ),
            ]);

        foreach ($this->roles() as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['slug' => Str::slug($roleName)],
                ['name' => $roleName, 'description' => $roleName.' application role'],
            );

            if ($roleName === 'Super Administrator') {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
            }
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@kingdomhub.test'],
            [
                'church_id' => $church->id,
                'campus_id' => $campus->id,
                'name' => 'Pastor John',
                'title' => 'Senior Pastor',
                'password' => Hash::make('password'),
            ],
        );

        $admin->roles()->syncWithoutDetaching(Role::query()->where('slug', 'super-administrator')->value('id'));

        Member::factory()
            ->count(12)
            ->state(['church_id' => $church->id, 'campus_id' => $campus->id])
            ->create();
    }

    private function roles(): array
    {
        return [
            'Super Administrator',
            'Church Administrator',
            'Senior Pastor',
            'Branch Pastor',
            'Finance Officer',
            'Membership Officer',
            'Ministry Leader',
            'Book Store Manager',
            'Asset Manager',
            'HR Manager',
            'Staff',
            'Volunteer',
            'Viewer',
        ];
    }
}
