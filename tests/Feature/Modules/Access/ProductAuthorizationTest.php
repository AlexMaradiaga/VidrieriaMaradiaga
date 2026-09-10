<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Access;

use App\Models\User;
use Database\Seeders\AccessRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ProductAuthorizationTest extends TestCase
{
    public function test_product_creation_requires_permission(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $connection = DB::connection();

        $this->assertSame('sqlsrv', $connection->getDriverName());

        $database = $connection
            ->selectOne('SELECT DB_NAME() AS database_name');

        $this->assertSame(
            'DB_VidrieriaMaradiaga_Test',
            $database->database_name
        );

        $connection->beginTransaction();

        try {
            $this->seed(AccessRolesSeeder::class);

            // Un visitante no puede acceder al endpoint.
            $this->postJson('/inventory/products', [])
                ->assertUnauthorized();

            $user = User::create([
                'name' => 'Usuario de prueba',
                'email' => 'test-'.bin2hex(random_bytes(8)).'@example.com',
                'password' => Hash::make(bin2hex(random_bytes(24))),
            ]);

            // Una cuenta sin roles tampoco puede crear productos.
            $this->actingAs($user, 'web')
                ->postJson('/inventory/products', [])
                ->assertForbidden();

            $user->assignRole('Ventas');

            $this->assertTrue(
                $user->can('inventory.products.view')
            );

            $this->assertFalse(
                $user->can('inventory.products.create')
            );

            $this->postJson('/inventory/products', [])
                ->assertForbidden();

            // Solo en esta cuenta temporal sustituimos el rol.
            $user->syncRoles(['Bodega']);

            $this->assertTrue(
                $user->can('inventory.products.create')
            );

            $this->assertFalse(
                $user->can('inventory.products.toggle-active')
            );

            // 422 significa que superó la autorización y llegó
            // a validar el formulario vacío.
            $this->postJson('/inventory/products', [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['sku', 'name']);

            $user->syncRoles(['Administrador']);

            $this->assertTrue(
                $user->can('inventory.products.toggle-active')
            );

            $this->postJson('/inventory/products', [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['sku', 'name']);
        } finally {
            $connection->rollBack();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
