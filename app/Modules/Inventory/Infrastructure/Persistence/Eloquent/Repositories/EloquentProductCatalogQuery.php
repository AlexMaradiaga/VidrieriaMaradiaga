<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Inventory\Application\Ports\ProductCatalogQueryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryProductModel;
use Illuminate\Support\Facades\DB;

final class EloquentProductCatalogQuery implements ProductCatalogQueryInterface
{
    public function search(
        string $search,
        ?int $categoryId,
        string $status,
        string $sort,
        int $perPage,
        int $page,
    ): array {
        $search = trim($search);

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
                'inventory_products.barcode',
                'inventory_products.description',
                'inventory_products.minimum_stock',
                'inventory_products.maximum_stock',
                'inventory_products.reorder_point',
                'categories.name as category_name',
                'units.symbol as unit_symbol',
            ])
            ->selectSub(
                DB::table('inventory_stock_balances')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn(
                        'inventory_stock_balances.product_id',
                        'inventory_products.id'
                    ),
                'stock_quantity'
            )
            ->withCasts([
                'stock_quantity' => 'decimal:4',
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

        if ($categoryId !== null) {
            $query->where('inventory_products.category_id', $categoryId);
        }

        if ($status === 'active') {
            $query->where('inventory_products.active', true);
        } elseif ($status === 'inactive') {
            $query->where('inventory_products.active', false);
        }

        match ($sort) {
            'sku_desc' => $query->orderByDesc('inventory_products.sku'),
            'name_asc' => $query->orderBy('inventory_products.name')->orderBy('inventory_products.sku'),
            'name_desc' => $query->orderByDesc('inventory_products.name')->orderBy('inventory_products.sku'),
            'category_asc' => $query->orderBy('categories.name')->orderBy('inventory_products.sku'),
            default => $query->orderBy('inventory_products.sku'),
        };

        $paginator = $query->paginate(
            max(15, min(100, $perPage)),
            ['*'],
            'page',
            max(1, $page),
        );

        return [
            'data' => $paginator->getCollection()
                ->map(fn (InventoryProductModel $product): array => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category_name' => $product->category_name,
                    'unit_symbol' => $product->unit_symbol,
                    'stock_quantity' => $product->stock_quantity,
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
