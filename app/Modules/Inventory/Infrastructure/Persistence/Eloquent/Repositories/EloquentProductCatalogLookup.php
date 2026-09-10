<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Inventory\Application\Ports\ProductCatalogLookupInterface;
use Illuminate\Support\Facades\DB;

final class EloquentProductCatalogLookup implements ProductCatalogLookupInterface
{
    public function activeCategoryExists(int $categoryId): bool
    {
        return DB::table('inventory_categories')
            ->where('id', $categoryId)
            ->where('active', true)
            ->exists();
    }

    public function activeUnitExists(int $unitId): bool
    {
        return DB::table('inventory_units')
            ->where('id', $unitId)
            ->where('active', true)
            ->exists();
    }
}
