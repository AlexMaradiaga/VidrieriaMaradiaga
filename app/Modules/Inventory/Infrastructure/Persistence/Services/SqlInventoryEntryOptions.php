<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Services;

use App\Modules\Inventory\Application\Ports\InventoryEntryOptionsInterface;
use Illuminate\Support\Facades\DB;

final class SqlInventoryEntryOptions implements InventoryEntryOptionsInterface
{
    public function get(): array
    {
        $products = DB::table('inventory_products as products')
            ->join(
                'inventory_units as units',
                'units.id',
                '=',
                'products.base_unit_id'
            )
            ->where('products.active', true)
            ->where('products.track_stock', true)
            ->where('products.track_lots', false)
            ->where('products.track_remnants', false)
            ->whereNull('products.deleted_at')
            ->where('units.active', true)
            ->whereNull('units.deleted_at')
            ->orderBy('products.name')
            ->get([
                'products.id',
                'products.sku',
                'products.name',
                'products.base_unit_id',
                'units.name as base_unit_name',
                'units.symbol as base_unit_symbol',
            ]);

        $conversions = DB::table('inventory_product_units as conversions')
            ->join(
                'inventory_units as units',
                'units.id',
                '=',
                'conversions.unit_id'
            )
            ->where('conversions.active', true)
            ->where('conversions.is_purchase_unit', true)
            ->where('conversions.conversion_factor_to_base', '>', 0)
            ->where('units.active', true)
            ->whereNull('units.deleted_at')
            ->get([
                'conversions.product_id',
                'conversions.unit_id',
                'conversions.conversion_factor_to_base',
                'units.name',
                'units.symbol',
            ])
            ->groupBy('product_id');

        return [
            'products' => $products->map(function (object $product) use ($conversions): array {
                $units = [[
                    'id' => (int) $product->base_unit_id,
                    'name' => $product->base_unit_name,
                    'symbol' => $product->base_unit_symbol,
                    'factor' => '1.00000000',
                ]];

                foreach ($conversions->get($product->id, collect()) as $conversion) {
                    if ((int) $conversion->unit_id === (int) $product->base_unit_id) {
                        continue;
                    }

                    $units[] = [
                        'id' => (int) $conversion->unit_id,
                        'name' => $conversion->name,
                        'symbol' => $conversion->symbol,
                        'factor' => (string) $conversion->conversion_factor_to_base,
                    ];
                }

                return [
                    'id' => (int) $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'base_unit_symbol' => $product->base_unit_symbol,
                    'units' => $units,
                ];
            })->all(),

            'locations' => DB::table('inventory_locations as locations')
                ->join(
                    'inventory_warehouses as warehouses',
                    'warehouses.id',
                    '=',
                    'locations.warehouse_id'
                )
                ->where('locations.active', true)
                ->whereNull('locations.deleted_at')
                ->where('warehouses.active', true)
                ->whereNull('warehouses.deleted_at')
                ->orderBy('warehouses.name')
                ->orderBy('locations.name')
                ->get([
                    'locations.id',
                    'locations.name',
                    'warehouses.name as warehouse_name',
                ])
                ->map(fn (object $location): array => [
                    'id' => (int) $location->id,
                    'name' => $location->warehouse_name.' / '.$location->name,
                ])
                ->all(),

            'suppliers' => DB::table('inventory_suppliers')
                ->where('active', true)
                ->whereNull('deleted_at')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name'])
                ->map(fn (object $supplier): array => [
                    'id' => (int) $supplier->id,
                    'name' => $supplier->legal_name,
                ])
                ->all(),

            'today' => now()->toDateString(),
        ];
    }
}
