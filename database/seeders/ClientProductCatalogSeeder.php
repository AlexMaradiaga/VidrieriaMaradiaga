<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ClientProductCatalogSeeder extends Seeder
{
    private const EXPECTED_PRODUCTS = 301;

    public function run(): void
    {
        $this->call(InventoryCatalogSeeder::class);

        $catalog = $this->catalog();
        $existingCount = DB::table('inventory_products')->count();
        $importedCount = DB::table('inventory_products')
            ->where('sku', 'like', 'VM-%')
            ->count();

        $categoryIds = DB::table('inventory_categories')
            ->whereIn('code', ['ALUMINUM', 'PVC', 'GLASS', 'ACCESSORIES', 'HARDWARE', 'SEALANTS'])
            ->pluck('id', 'code');
        $unitIds = DB::table('inventory_units')
            ->whereIn('code', ['UNIT', 'METER', 'BAR', 'SHEET', 'TUBE'])
            ->pluck('id', 'code');

        foreach ($catalog as $product) {
            if (! $categoryIds->has($product['category_code']) || ! $unitIds->has($product['unit_code'])) {
                throw new RuntimeException(
                    "No existe la categoría o unidad requerida por {$product['sku']}."
                );
            }
        }

        if ($existingCount === self::EXPECTED_PRODUCTS && $importedCount === self::EXPECTED_PRODUCTS) {
            $this->synchronizeInstalledCatalog($catalog, $categoryIds->all(), $unitIds->all());
            $this->command?->info('Catálogo sincronizado: 301 productos disponibles para entradas y categorías corregidas.');

            return;
        }

        if (! in_array($existingCount, [0, 2], true)) {
            throw new RuntimeException(
                "Importación cancelada: se esperaban 0, 2 o los 301 productos VM y se encontraron {$existingCount}."
            );
        }

        DB::transaction(function () use ($catalog, $categoryIds, $unitIds): void {
            $productIds = DB::table('inventory_products')->pluck('id');

            if ($productIds->isNotEmpty()) {
                $this->ensureProductsHaveNoCommercialHistory($productIds->all());
                $this->removeDemoInventoryHistory($productIds->all());
            }

            DB::table('inventory_products')->delete();
            DB::statement("DBCC CHECKIDENT ('inventory_products', RESEED, 0)");

            $now = now();
            $rows = array_map(
                static fn (array $product): array => [
                    'category_id' => $categoryIds[$product['category_code']],
                    'base_unit_id' => $unitIds[$product['unit_code']],
                    'sku' => $product['sku'],
                    'barcode' => null,
                    'name' => $product['name'],
                    'description' => null,
                    'product_type' => 'material',
                    'minimum_stock' => 0,
                    'maximum_stock' => null,
                    'reorder_point' => 0,
                    'average_cost' => 0,
                    'last_purchase_cost' => 0,
                    'sale_price' => number_format((float) $product['sale_price'], 4, '.', ''),
                    'track_stock' => true,
                    'track_lots' => false,
                    'track_remnants' => $product['track_remnants'],
                    'allow_negative_stock' => false,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
                $catalog
            );

            // Las inserciones individuales evitan dos particularidades de SQL Server:
            // el máximo de 2100 parámetros y la inferencia de tipos en VALUES múltiples.
            // La transacción conserva la operación completa como una sola unidad atómica.
            foreach ($rows as $row) {
                DB::table('inventory_products')->insert($row);
            }

            $inserted = DB::table('inventory_products')->count();

            if ($inserted !== self::EXPECTED_PRODUCTS) {
                throw new RuntimeException(
                    "La importación insertó {$inserted} productos; se esperaban ".self::EXPECTED_PRODUCTS.'.'
                );
            }
        });

        $this->command?->info('Catálogo instalado correctamente: 301 productos, IDs del 1 al 301.');
    }

    /**
     * Actualiza la clasificación del catálogo ya instalado sin borrar existencias,
     * movimientos, costos promedio ni relaciones comerciales.
     *
     * @param  array<int, array<string, int|float|string|bool>>  $catalog
     * @param  array<string, int>  $categoryIds
     * @param  array<string, int>  $unitIds
     */
    private function synchronizeInstalledCatalog(
        array $catalog,
        array $categoryIds,
        array $unitIds,
    ): void {
        DB::transaction(function () use ($catalog, $categoryIds, $unitIds): void {
            $now = now();

            foreach ($catalog as $product) {
                $updated = DB::table('inventory_products')
                    ->where('sku', $product['sku'])
                    ->whereNull('deleted_at')
                    ->update([
                        'category_id' => $categoryIds[$product['category_code']],
                        'base_unit_id' => $unitIds[$product['unit_code']],
                        'name' => $product['name'],
                        'sale_price' => number_format((float) $product['sale_price'], 4, '.', ''),
                        'track_stock' => true,
                        'track_lots' => false,
                        // El control de retazos se habilita manualmente cuando el
                        // negocio decida administrar cada pieza por sus medidas.
                        'track_remnants' => false,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new RuntimeException(
                        "No fue posible sincronizar el producto {$product['sku']}."
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array<string, int|float|string|bool>>
     */
    private function catalog(): array
    {
        $path = database_path('data/vidrieria_maradiaga_products.json');

        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el catálogo: {$path}");
        }

        /** @var array<int, array<string, int|float|string|bool>> $catalog */
        $catalog = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (count($catalog) !== self::EXPECTED_PRODUCTS) {
            throw new RuntimeException('El archivo del catálogo no contiene los 301 productos esperados.');
        }

        return $catalog;
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function ensureProductsHaveNoCommercialHistory(array $productIds): void
    {
        foreach ([
            'sales_lines' => 'ventas',
            'purchasing_lines' => 'compras',
        ] as $table => $label) {
            if (
                Schema::hasTable($table)
                && DB::table($table)->whereIn('product_id', $productIds)->exists()
            ) {
                throw new RuntimeException(
                    "Importación cancelada: existen {$label} asociadas a los productos actuales."
                );
            }
        }
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function removeDemoInventoryHistory(array $productIds): void
    {
        $this->deleteByProduct('inventory_transfers', $productIds);
        $this->deleteByProduct('inventory_count_lines', $productIds);
        $this->deleteOrphans('inventory_counts', 'inventory_count_lines', 'count_id');
        $this->deleteByProduct('inventory_remnants', $productIds);
        $this->deleteByProduct('inventory_kit_items', $productIds);
        $this->deleteOrphans('inventory_kits', 'inventory_kit_items', 'kit_id');
        $this->deleteByProduct('inventory_supplier_products', $productIds);
        $this->deleteByProduct('inventory_product_units', $productIds);
        $this->deleteByProduct('inventory_product_attributes', $productIds);
        $this->deleteByProduct('inventory_stock_balances', $productIds);
        $this->deleteByProduct('inventory_movement_lines', $productIds);
        $this->deleteOrphans('inventory_movements', 'inventory_movement_lines', 'movement_id');
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function deleteByProduct(string $table, array $productIds): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->whereIn('product_id', $productIds)->delete();
        }
    }

    private function deleteOrphans(string $parentTable, string $childTable, string $foreignKey): void
    {
        if (! Schema::hasTable($parentTable) || ! Schema::hasTable($childTable)) {
            return;
        }

        DB::table($parentTable)
            ->whereNotExists(static function ($query) use ($parentTable, $childTable, $foreignKey): void {
                $query->select(DB::raw(1))
                    ->from($childTable)
                    ->whereColumn("{$childTable}.{$foreignKey}", "{$parentTable}.id");
            })
            ->delete();
    }
}
