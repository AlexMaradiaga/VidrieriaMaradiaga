<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Infrastructure\Persistence\Queries\SqlInventoryDashboardQuery;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryDashboardQueryTest extends TestCase
{
    public function test_it_sums_locations_and_keeps_inactive_stock_in_valuation(): void
    {
        // Comprobar destino antes de iniciar cualquier escritura de fixtures.
        if (DB::connection()->getDriverName() !== 'sqlsrv'
            || !str_ends_with(strtolower(DB::connection()->getDatabaseName()), '_test')) {
            $this->markTestSkipped('Requiere SQL Server y una base terminada en _Test.');
        }

        DB::beginTransaction();
        try {
            $query = new SqlInventoryDashboardQuery();
            $before = $query->get();
            $code = 'DASH-'.bin2hex(random_bytes(4));
            $now = now();
            $timestamps = ['created_at' => $now, 'updated_at' => $now];
            $unit = DB::table('inventory_units')->insertGetId([
                'code' => $code, 'name' => 'Unidad de prueba', 'symbol' => 'u',
                ...$timestamps,
            ]);
            $category = DB::table('inventory_categories')->insertGetId([
                'code' => $code, 'name' => 'Categoría de prueba', ...$timestamps,
            ]);
            $warehouse = DB::table('inventory_warehouses')->insertGetId([
                'code' => $code, 'name' => 'Bodega de prueba', ...$timestamps,
            ]);
            $locations = [];
            foreach (['A', 'B'] as $suffix) {
                $locations[] = DB::table('inventory_locations')->insertGetId([
                    'warehouse_id' => $warehouse, 'code' => $suffix,
                    'name' => 'Ubicación '.$suffix, ...$timestamps,
                ]);
            }
            $base = [
                'category_id' => $category, 'base_unit_id' => $unit,
                'name' => 'Producto de prueba', 'minimum_stock' => '4',
                'average_cost' => '2', 'track_stock' => true, 'active' => true,
                ...$timestamps,
            ];
            $active = DB::table('inventory_products')->insertGetId(['sku' => $code.'-A', ...$base]);
            $inactive = DB::table('inventory_products')->insertGetId([
                ...$base, 'sku' => $code.'-I', 'active' => false, 'average_cost' => '3',
            ]);
            $deleted = DB::table('inventory_products')->insertGetId([
                ...$base, 'sku' => $code.'-D', 'deleted_at' => $now,
            ]);

            // Sin saldos, el producto activo tiene cero y debe generar alerta.
            $this->assertSame($before['critical_products'] + 1, $query->get()['critical_products']);

            DB::table('inventory_stock_balances')->insert([
                ['product_id' => $active, 'location_id' => $locations[0], 'quantity' => '2', ...$timestamps],
                ['product_id' => $active, 'location_id' => $locations[1], 'quantity' => '3', ...$timestamps],
                ['product_id' => $inactive, 'location_id' => $locations[0], 'quantity' => '10', ...$timestamps],
                ['product_id' => $deleted, 'location_id' => $locations[0], 'quantity' => '100', ...$timestamps],
            ]);

            $after = $query->get();
            $this->assertSame($before['products_total'] + 2, $after['products_total']);
            $this->assertSame($before['products_active'] + 1, $after['products_active']);
            $this->assertSame($before['critical_products'], $after['critical_products']);
            $this->assertSame($before['pending_entries'], $after['pending_entries']);
            $this->assertTrue(
                BigDecimal::of($after['inventory_value'])->isEqualTo(
                    BigDecimal::of($before['inventory_value'])->plus('40')
                )
            );

            // En el mínimo exacto también debe contarse una sola alerta.
            DB::table('inventory_products')->where('id', $active)->update(['minimum_stock' => '5']);
            $this->assertSame($before['critical_products'] + 1, $query->get()['critical_products']);
        } finally {
            DB::rollBack();
        }
    }
}
