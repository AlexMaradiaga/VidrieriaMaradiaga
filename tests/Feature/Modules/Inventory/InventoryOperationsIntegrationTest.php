<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Infrastructure\Persistence\Services\SqlInventoryOperations;
use App\Modules\Inventory\Infrastructure\Persistence\Services\SqlRegisterInventoryEntry;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryOperationsIntegrationTest extends TestCase
{
    public function test_entry_increases_stock_updates_average_cost_and_is_idempotent(): void
    {
        $this->requireInventoryTestDatabase();
        DB::beginTransaction();

        try {
            $fixture = $this->fixture('ENTRY');
            $service = new SqlRegisterInventoryEntry();
            $key = (string) Str::uuid();
            $payload = [
                'operation_key' => $key,
                'product_id' => $fixture['product'],
                'location_id' => $fixture['source'],
                'unit_id' => $fixture['unit'],
                'supplier_id' => null,
                'reason' => 'initial_balance',
                'document_date' => now()->toDateString(),
                'reference' => 'INICIAL-001',
                'notes' => null,
                'quantity' => '2.0000',
                'unit_cost' => '35.0000',
            ];

            $first = $service->register($payload, $fixture['user']);
            $second = $service->register($payload, $fixture['user']);

            $this->assertFalse($first['repeated']);
            $this->assertTrue($second['repeated']);
            $this->assertSame($first['movement_id'], $second['movement_id']);
            $this->assertSame(12.0, $this->balance($fixture['product'], $fixture['source']));
            $this->assertSame(26.6667, (float) DB::table('inventory_products')
                ->where('id', $fixture['product'])
                ->value('average_cost'));
            $this->assertSame(1, DB::table('inventory_movements')->where('operation_key', $key)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_exit_decreases_stock_is_idempotent_and_rejects_negative_balance(): void
    {
        $this->requireInventoryTestDatabase();
        DB::beginTransaction();

        try {
            $fixture = $this->fixture('EXIT');
            $service = new SqlInventoryOperations();
            $key = (string) Str::uuid();
            $payload = [
                'operation_key' => $key,
                'product_id' => $fixture['product'],
                'location_id' => $fixture['source'],
                'unit_id' => $fixture['unit'],
                'reason' => 'sale',
                'document_date' => now()->toDateString(),
                'reference' => 'FACT-001',
                'notes' => null,
                'quantity' => '3.0000',
            ];

            $first = $service->registerExit($payload, $fixture['user']);
            $second = $service->registerExit($payload, $fixture['user']);

            $this->assertFalse($first['repeated']);
            $this->assertTrue($second['repeated']);
            $this->assertSame($first['movement_id'], $second['movement_id']);
            $this->assertSame(7.0, $this->balance($fixture['product'], $fixture['source']));
            $this->assertSame(1, DB::table('inventory_movements')->where('operation_key', $key)->count());

            try {
                $service->registerExit([
                    ...$payload,
                    'operation_key' => (string) Str::uuid(),
                    'quantity' => '8.0000',
                ], $fixture['user']);
                $this->fail('La salida debía rechazarse por existencia insuficiente.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('existencia suficiente', $exception->getMessage());
            }

            $this->assertSame(7.0, $this->balance($fixture['product'], $fixture['source']));
        } finally {
            DB::rollBack();
        }
    }

    public function test_transfer_moves_stock_atomically_and_is_idempotent(): void
    {
        $this->requireInventoryTestDatabase();
        DB::beginTransaction();

        try {
            $fixture = $this->fixture('TRANSFER');
            $service = new SqlInventoryOperations();
            $key = (string) Str::uuid();
            $payload = [
                'operation_key' => $key,
                'product_id' => $fixture['product'],
                'source_location_id' => $fixture['source'],
                'destination_location_id' => $fixture['destination'],
                'document_date' => now()->toDateString(),
                'reference' => 'TRAS-001',
                'notes' => 'Traslado de prueba',
                'quantity' => '4.0000',
            ];

            $first = $service->transfer($payload, $fixture['user']);
            $second = $service->transfer($payload, $fixture['user']);

            $this->assertFalse($first['repeated']);
            $this->assertTrue($second['repeated']);
            $this->assertSame($first['transfer_id'], $second['transfer_id']);
            $this->assertSame(6.0, $this->balance($fixture['product'], $fixture['source']));
            $this->assertSame(4.0, $this->balance($fixture['product'], $fixture['destination']));
            $transfer = DB::table('inventory_transfers')->where('id', $first['transfer_id'])->first();
            $this->assertNotNull($transfer);
            $this->assertSame(2, DB::table('inventory_movements')
                ->whereIn('id', [$transfer->outbound_movement_id, $transfer->inbound_movement_id])
                ->where('reason', 'transfer')
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_physical_count_replaces_balance_and_documents_variance_once(): void
    {
        $this->requireInventoryTestDatabase();
        DB::beginTransaction();

        try {
            $fixture = $this->fixture('COUNT');
            $service = new SqlInventoryOperations();
            $key = (string) Str::uuid();
            $payload = [
                'operation_key' => $key,
                'location_id' => $fixture['source'],
                'document_date' => now()->toDateString(),
                'notes' => 'Conteo de prueba',
                'lines' => [[
                    'product_id' => $fixture['product'],
                    'counted_quantity' => '12.0000',
                ]],
            ];

            $first = $service->registerCount($payload, $fixture['user']);
            $second = $service->registerCount($payload, $fixture['user']);

            $this->assertFalse($first['repeated']);
            $this->assertTrue($second['repeated']);
            $this->assertSame($first['count_id'], $second['count_id']);
            $this->assertSame(12.0, $this->balance($fixture['product'], $fixture['source']));
            $line = DB::table('inventory_count_lines')->where('count_id', $first['count_id'])->first();
            $this->assertNotNull($line);
            $this->assertSame(2.0, (float) $line->variance_quantity);
            $this->assertNotNull($line->movement_id);
            $this->assertSame(1, DB::table('inventory_count_lines')->where('count_id', $first['count_id'])->count());
        } finally {
            DB::rollBack();
        }
    }

    private function requireInventoryTestDatabase(): void
    {
        app()->setLocale('es');

        if (DB::connection()->getDriverName() !== 'sqlsrv'
            || ! str_ends_with(strtolower(DB::connection()->getDatabaseName()), '_test')) {
            $this->markTestSkipped('Requiere SQL Server y una base terminada en _Test.');
        }
    }

    /** @return array{user:int,unit:int,product:int,source:int,destination:int} */
    private function fixture(string $prefix): array
    {
        $code = $prefix.'-'.strtoupper(bin2hex(random_bytes(4)));
        $now = now();
        $timestamps = ['created_at' => $now, 'updated_at' => $now];
        $user = DB::table('users')->insertGetId([
            'name' => 'Usuario de prueba',
            'email' => strtolower($code).'@example.test',
            'password' => bcrypt('test-password'),
            ...$timestamps,
        ]);
        $unit = DB::table('inventory_units')->insertGetId([
            'code' => substr($code, 0, 20),
            'name' => 'Unidad de prueba',
            'symbol' => 'ud',
            ...$timestamps,
        ]);
        $category = DB::table('inventory_categories')->insertGetId([
            'code' => substr($code, 0, 30),
            'name' => 'Categoría de prueba',
            ...$timestamps,
        ]);
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => substr($code, 0, 30),
            'name' => 'Bodega de prueba',
            ...$timestamps,
        ]);
        $source = DB::table('inventory_locations')->insertGetId([
            'warehouse_id' => $warehouse,
            'code' => 'ORIGEN',
            'name' => 'Origen',
            ...$timestamps,
        ]);
        $destination = DB::table('inventory_locations')->insertGetId([
            'warehouse_id' => $warehouse,
            'code' => 'DESTINO',
            'name' => 'Destino',
            ...$timestamps,
        ]);
        $product = DB::table('inventory_products')->insertGetId([
            'category_id' => $category,
            'base_unit_id' => $unit,
            'sku' => substr($code.'-PRODUCTO', 0, 50),
            'name' => 'Producto de prueba',
            'average_cost' => '25.0000',
            'track_stock' => true,
            'active' => true,
            ...$timestamps,
        ]);
        DB::table('inventory_stock_balances')->insert([
            'product_id' => $product,
            'location_id' => $source,
            'quantity' => '10.0000',
            ...$timestamps,
        ]);

        return compact('user', 'unit', 'product', 'source', 'destination');
    }

    private function balance(int $productId, int $locationId): float
    {
        return (float) DB::table('inventory_stock_balances')
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->value('quantity');
    }
}
