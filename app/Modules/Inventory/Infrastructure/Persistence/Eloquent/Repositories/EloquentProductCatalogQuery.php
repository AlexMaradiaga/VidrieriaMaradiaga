<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Inventory\Application\Ports\ProductCatalogQueryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryProductModel;
use Illuminate\Support\Facades\DB;

final class EloquentProductCatalogQuery implements ProductCatalogQueryInterface
{
    public function search(string $search, int $page): array
    {
        $query = InventoryProductModel::query()
            ->leftJoin(
                'inventory_categories as categories',
                'categories.id',
                '=',
                'inventory_products.category_id'
            )
            ->leftJoin(
                'inventory_units as units',
                'units.id',
                '=',
                'inventory_products.base_unit_id'
            )
            ->select([
                'inventory_products.id',
                'inventory_products.sku',
                'inventory_products.name',
                'inventory_products.sale_price',
                'inventory_products.active',
                'categories.name as category_name',
                'units.symbol as unit_symbol',
                'inventory_products.barcode',
                'inventory_products.description',
                'inventory_products.minimum_stock',
                'inventory_products.maximum_stock',
                'inventory_products.reorder_point',
            ]);

        if ($search !== '') {
            // Tratar los comodines de SQL Server como texto literal.
            $escaped = str_replace(
                ['[', '%', '_'],
                ['[[]', '[%]', '[_]'],
                $search
            );

            $pattern = '%'.$escaped.'%';

            $query->where(function ($filter) use ($pattern): void {
                $filter
                    ->where('inventory_products.sku', 'like', $pattern)
                    ->orWhere('inventory_products.name', 'like', $pattern);
            });
        }

        $paginator = $query
            ->orderByDesc('inventory_products.id')
            ->paginate(15, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (InventoryProductModel $product): array => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category_name' => $product->category_name,
                    'unit_symbol' => $product->unit_symbol,
                    'sale_price' => $product->sale_price,
                    'active' => $product->active,
                    'barcode' => $product->barcode,
                    'description' => $product->description,
                    'minimum_stock' => $product->minimum_stock,
                    'maximum_stock' => $product->maximum_stock,
                    'reorder_point' => $product->reorder_point,
                ])
                ->values()
                ->all(),

            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function formOptions(): array
    {
        return [
            'categories' => DB::table('inventory_categories')
                ->where('active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (object $item): array => [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                ])
                ->all(),

            'units' => DB::table('inventory_units')
                ->where('active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'symbol'])
                ->map(fn (object $item): array => [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'symbol' => $item->symbol,
                ])
                ->all(),
        ];
    }
}
