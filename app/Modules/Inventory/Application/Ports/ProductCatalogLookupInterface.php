<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface ProductCatalogLookupInterface
{
    public function activeCategoryExists(int $categoryId): bool;

    public function activeUnitExists(int $unitId): bool;
}
