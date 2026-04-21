<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $email = trim((string) env('ADMIN_EMAIL', 'admin@fincompta.dz'));

        $password = env('ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            if (app()->environment('local')) {
                $password = 'password';
            } else {
                $this->command->warn('ADMIN_PASSWORD is empty; skipping admin user seed (set ADMIN_EMAIL and ADMIN_PASSWORD in .env).');

                return;
            }
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) env('ADMIN_NAME', 'Administrateur')),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web'],
        );

        // Keep admin role permissions in sync even when only this seeder is run.
        $adminRole->syncPermissions(
            Permission::query()->where('guard_name', 'web')->get()
        );

        $user->syncRoles([$adminRole]);

        $this->command->info('Admin user ready: '.$email);
    }
}
