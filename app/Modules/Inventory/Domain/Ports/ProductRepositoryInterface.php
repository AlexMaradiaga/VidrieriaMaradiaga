<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Ports;

use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\ValueObjects\Sku;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySku(Sku $sku): ?Product;

    public function existsBySku(
        Sku $sku,
        ?int $excludingProductId = null,
    ): bool;

    public function save(Product $product): Product;

    public function delete(Product $product): void;

    public function updateCatalogDetails(Product $product): Product;

    public function updateActiveStatus(Product $product): Product;
}
