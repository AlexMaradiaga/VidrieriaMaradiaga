<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AccessRolesSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = [
                'inventory.products.view',
                'inventory.products.create',
                'inventory.products.update',
                'inventory.products.toggle-active',
                'inventory.entries.create',
                'inventory.entries.post',
            ];

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $roles = [
                'Administrador' => $permissions,

                'Bodega' => [
                    'inventory.products.view',
                    'inventory.products.create',
                    'inventory.products.update',
                    'inventory.entries.create',
                    'inventory.entries.post',
                ],

                'Ventas' => [
                    'inventory.products.view',
                ],
            ];

            foreach ($roles as $name => $rolePermissions) {
                $role = Role::findOrCreate($name, 'web');

                $role->givePermissionTo($rolePermissions);
            }
        });

        $registrar->forgetCachedPermissions();
    }
}
