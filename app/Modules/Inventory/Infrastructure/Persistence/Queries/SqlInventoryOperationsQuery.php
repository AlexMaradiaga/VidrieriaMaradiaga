<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Queries;

use App\Modules\Inventory\Application\Ports\InventoryOperationsQueryInterface;
use Illuminate\Support\Facades\DB;

final class SqlInventoryOperationsQuery implements InventoryOperationsQueryInterface
{
    public function options(): array
    {
        $products = DB::table('inventory_products as products')
            ->join('inventory_units as units', 'units.id', '=', 'products.base_unit_id')
            ->where('products.active', true)
            ->where('products.track_stock', true)
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')
            ->get([
                'products.id', 'products.sku', 'products.name', 'products.base_unit_id',
                'products.average_cost', 'products.minimum_stock',
                'units.name as base_unit_name', 'units.symbol as base_unit_symbol',
            ]);

        $issueUnits = DB::table('inventory_product_units as conversions')
            ->join('inventory_units as units', 'units.id', '=', 'conversions.unit_id')
            ->where('conversions.active', true)
            ->where('conversions.is_issue_unit', true)
            ->where('conversions.conversion_factor_to_base', '>', 0)
            ->where('units.active', true)
            ->whereNull('units.deleted_at')
            ->get([
                'conversions.product_id', 'conversions.unit_id',
                'conversions.conversion_factor_to_base', 'units.name', 'units.symbol',
            ])->groupBy('product_id');

        $locations = DB::table('inventory_locations as locations')
            ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
            ->where('locations.active', true)
            ->whereNull('locations.deleted_at')
            ->where('warehouses.active', true)
            ->whereNull('warehouses.deleted_at')
            ->orderBy('warehouses.name')->orderBy('locations.name')
            ->get(['locations.id', 'locations.name', 'warehouses.name as warehouse_name'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->warehouse_name.' / '.$row->name,
            ])->all();

        return [
            'products' => $products->map(function (object $product) use ($issueUnits): array {
                $units = [[
                    'id' => (int) $product->base_unit_id,
                    'name' => $product->base_unit_name,
                    'symbol' => $product->base_unit_symbol,
                    'factor' => '1.00000000',
                ]];

                foreach ($issueUnits->get($product->id, collect()) as $unit) {
                    if ((int) $unit->unit_id === (int) $product->base_unit_id) {
                        continue;
                    }

                    $units[] = [
                        'id' => (int) $unit->unit_id,
                        'name' => $unit->name,
                        'symbol' => $unit->symbol,
                        'factor' => (string) $unit->conversion_factor_to_base,
                    ];
                }

                return [
                    'id' => (int) $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'base_unit_symbol' => $product->base_unit_symbol,
                    'average_cost' => (string) $product->average_cost,
                    'minimum_stock' => (string) $product->minimum_stock,
                    'units' => $units,
                ];
            })->all(),
            'locations' => $locations,
            'today' => now()->toDateString(),
        ];
    }

    public function alerts(): array
    {
        $stock = DB::table('inventory_stock_balances')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id');

        $rows = DB::table('inventory_products as products')
            ->join('inventory_units as units', 'units.id', '=', 'products.base_unit_id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->where('products.active', true)
            ->where('products.track_stock', true)
            ->whereNull('products.deleted_at')
            ->whereRaw('COALESCE(stock.quantity, 0) <= products.reorder_point')
            ->orderByRaw('COALESCE(stock.quantity, 0) ASC')
            ->get([
                'products.id', 'products.sku', 'products.name', 'units.symbol',
                'products.minimum_stock', 'products.reorder_point', 'products.maximum_stock',
                DB::raw('COALESCE(stock.quantity, 0) as quantity'),
            ])->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'name' => $row->name,
                'unit' => $row->symbol,
                'quantity' => (string) $row->quantity,
                'minimum_stock' => (string) $row->minimum_stock,
                'reorder_point' => (string) $row->reorder_point,
                'maximum_stock' => $row->maximum_stock === null ? null : (string) $row->maximum_stock,
            ])->all();

        return [
            'rows' => $rows,
            'out_of_stock' => collect($rows)->where('quantity', '<=', 0)->count(),
            'total' => count($rows),
        ];
    }

    public function counts(): array
    {
        return [
            ...$this->options(),
            'counts' => DB::table('inventory_counts as counts')
                ->join('inventory_locations as locations', 'locations.id', '=', 'counts.location_id')
                ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
                ->join('users', 'users.id', '=', 'counts.created_by')
                ->leftJoin('inventory_count_lines as lines', 'lines.count_id', '=', 'counts.id')
                ->groupBy('counts.id', 'counts.document_date', 'counts.status', 'counts.notes', 'counts.created_at', 'locations.name', 'warehouses.name', 'users.name')
                ->orderByDesc('counts.id')
                ->limit(100)
                ->get([
                    'counts.id', 'counts.document_date', 'counts.status', 'counts.notes', 'counts.created_at',
                    'locations.name as location', 'warehouses.name as warehouse', 'users.name as creator',
                    DB::raw('COUNT(lines.id) as line_count'),
                    DB::raw('COALESCE(SUM(ABS(lines.variance_quantity)), 0) as total_variance'),
                ])->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'document_date' => (string) $row->document_date,
                    'status' => $row->status,
                    'notes' => $row->notes,
                    'location' => $row->warehouse.' / '.$row->location,
                    'creator' => $row->creator,
                    'line_count' => (int) $row->line_count,
                    'total_variance' => (string) $row->total_variance,
                ])->all(),
        ];
    }

    public function remnants(): array
    {
        return [
            ...$this->options(),
            'remnants' => DB::table('inventory_remnants as remnants')
                ->join('inventory_products as products', 'products.id', '=', 'remnants.product_id')
                ->join('inventory_locations as locations', 'locations.id', '=', 'remnants.location_id')
                ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
                ->orderByRaw("CASE WHEN remnants.status = 'available' THEN 0 ELSE 1 END")
                ->orderByDesc('remnants.id')
                ->get([
                    'remnants.id', 'remnants.code', 'remnants.width_mm', 'remnants.height_mm',
                    'remnants.thickness_mm', 'remnants.quantity', 'remnants.status', 'remnants.notes',
                    'products.sku', 'products.name as product', 'locations.name as location',
                    'warehouses.name as warehouse',
                ])->map(fn (object $row): array => [
                    ...((array) $row),
                    'id' => (int) $row->id,
                    'quantity' => (int) $row->quantity,
                    'width_mm' => (string) $row->width_mm,
                    'height_mm' => (string) $row->height_mm,
                    'thickness_mm' => $row->thickness_mm === null ? null : (string) $row->thickness_mm,
                    'area_m2' => number_format(((float) $row->width_mm * (float) $row->height_mm * (int) $row->quantity) / 1_000_000, 4, '.', ''),
                ])->all(),
        ];
    }

    public function kits(): array
    {
        $stock = DB::table('inventory_stock_balances')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id');

        $kits = DB::table('inventory_kits')->orderBy('name')->get();

        return [
            'products' => $this->options()['products'],
            'kits' => $kits->map(function (object $kit) use ($stock): array {
                $items = DB::table('inventory_kit_items as items')
                    ->join('inventory_products as products', 'products.id', '=', 'items.product_id')
                    ->join('inventory_units as units', 'units.id', '=', 'products.base_unit_id')
                    ->leftJoinSub(clone $stock, 'stock', 'stock.product_id', '=', 'products.id')
                    ->where('items.kit_id', $kit->id)
                    ->orderBy('products.name')
                    ->get([
                        'items.product_id', 'items.quantity', 'products.sku', 'products.name',
                        'units.symbol', DB::raw('COALESCE(stock.quantity, 0) as stock'),
                    ])->map(fn (object $item): array => [
                        'product_id' => (int) $item->product_id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'unit' => $item->symbol,
                        'quantity' => (string) $item->quantity,
                        'stock' => (string) $item->stock,
                    ])->all();

                $availability = empty($items) ? 0 : min(array_map(
                    fn (array $item): int => (int) floor((float) $item['stock'] / (float) $item['quantity']),
                    $items,
                ));

                return [
                    'id' => (int) $kit->id,
                    'code' => $kit->code,
                    'name' => $kit->name,
                    'description' => $kit->description,
                    'active' => (bool) $kit->active,
                    'availability' => $availability,
                    'items' => $items,
                ];
            })->all(),
        ];
    }

    public function catalogs(): array
    {
        return [
            'units' => DB::table('inventory_units')->whereNull('deleted_at')->orderBy('name')->get(),
            'categories' => DB::table('inventory_categories')->whereNull('deleted_at')->orderBy('sort_order')->orderBy('name')->get(),
            'suppliers' => DB::table('inventory_suppliers')->whereNull('deleted_at')->orderBy('legal_name')->get(),
            'warehouses' => DB::table('inventory_warehouses')->whereNull('deleted_at')->orderBy('name')->get(),
            'locations' => DB::table('inventory_locations as locations')
                ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
                ->whereNull('locations.deleted_at')->whereNull('warehouses.deleted_at')
                ->orderBy('warehouses.name')->orderBy('locations.name')
                ->get(['locations.*', 'warehouses.name as warehouse_name']),
            'product_units' => DB::table('inventory_product_units as conversions')
                ->join('inventory_products as products', 'products.id', '=', 'conversions.product_id')
                ->join('inventory_units as units', 'units.id', '=', 'conversions.unit_id')
                ->orderBy('products.name')->orderBy('units.name')
                ->get([
                    'conversions.*', 'products.sku', 'products.name as product_name',
                    'units.name as unit_name', 'units.symbol as unit_symbol',
                ]),
            'catalog_products' => DB::table('inventory_products')
                ->whereNull('deleted_at')->where('active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'catalog_units' => DB::table('inventory_units')
                ->whereNull('deleted_at')->where('active', true)->orderBy('name')->get(['id', 'name', 'symbol']),
        ];
    }
}
