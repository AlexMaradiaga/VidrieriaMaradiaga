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
                'inventory.entries.view',
                'inventory.kardex.view',
                'inventory.exits.create',
                'inventory.exits.post',
                'inventory.transfers.create',
                'inventory.counts.view',
                'inventory.counts.create',
                'inventory.alerts.view',
                'inventory.remnants.view',
                'inventory.remnants.manage',
                'inventory.kits.view',
                'inventory.kits.manage',
                'inventory.catalogs.view',
                'inventory.catalogs.manage',
                'inventory.cuts.use',
                'access.users.view',
                'access.users.manage',
                'access.roles.manage',
                'access.employees.manage',
                'suppliers.view',
                'suppliers.manage',
                'purchases.view',
                'purchases.create',
                'purchases.post',
                'purchases.payments.manage',
                'sales.view',
                'sales.create',
                'sales.post',
                'sales.payments.manage',
                'sales.customers.manage',
                'accounting.accounts.view',
                'accounting.accounts.manage',
                'accounting.periods.manage',
                'accounting.entries.view',
                'accounting.entries.create',
                'accounting.reports.view',
                'accounting.treasury.view',
                'accounting.treasury.manage',
                'accounting.expenses.view',
                'accounting.expenses.manage',
                'accounting.loans.view',
                'accounting.loans.manage',
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
                    'inventory.entries.view',
                    'inventory.kardex.view',
                    'inventory.exits.create',
                    'inventory.exits.post',
                    'inventory.transfers.create',
                    'inventory.counts.view',
                    'inventory.counts.create',
                    'inventory.alerts.view',
                    'inventory.remnants.view',
                    'inventory.remnants.manage',
                    'inventory.kits.view',
                    'inventory.catalogs.view',
                    'inventory.cuts.use',
                ],

                'Ventas' => [
                    'inventory.products.view',
                    'inventory.exits.create',
                    'inventory.exits.post',
                    'inventory.kits.view',
                    'sales.view',
                    'sales.create',
                    'sales.post',
                    'sales.payments.manage',
                    'sales.customers.manage',
                ],

                'Contabilidad' => [
                    'sales.view',
                    'suppliers.view',
                    'purchases.view',
                    'purchases.payments.manage',
                    'accounting.accounts.view',
                    'accounting.accounts.manage',
                    'accounting.periods.manage',
                    'accounting.entries.view',
                    'accounting.entries.create',
                    'accounting.reports.view',
                    'accounting.treasury.view',
                    'accounting.treasury.manage',
                    'accounting.expenses.view',
                    'accounting.expenses.manage',
                    'accounting.loans.view',
                    'accounting.loans.manage',
                ],

                'Compras' => [
                    'inventory.products.view',
                    'inventory.entries.view',
                    'suppliers.view',
                    'suppliers.manage',
                    'purchases.view',
                    'purchases.create',
                    'purchases.post',
                    'purchases.payments.manage',
                ],

                'Recursos Humanos' => [
                    'access.users.view',
                    'access.employees.manage',
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
