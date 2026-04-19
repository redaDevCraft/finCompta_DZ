<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.access',
            'payments.view',
            'payments.confirm',
            'subscriptions.view',
            'subscriptions.manage',
            'companies.view',
            'companies.manage',
            'users.view',
            'users.manage',
            'plans.view',
            'plans.manage',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web'],
        );

        $admin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());
    }
}
