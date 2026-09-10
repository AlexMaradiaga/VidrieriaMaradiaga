<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Handlers;

use App\Modules\Inventory\Application\Commands\CreateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductCreationException;
use App\Modules\Inventory\Application\Ports\ProductCatalogLookupInterface;
use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Enums\ProductType;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\Sku;

final class CreateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductCatalogLookupInterface $catalog,
    ) {
    }

    public function handle(CreateProductCommand $command): Product
    {
        $sku = Sku::fromString($command->sku);

        $productType = ProductType::tryFrom($command->productType);

        if ($productType === null) {
            throw ProductCreationException::invalidProductType();
        }

        if (
            ! $command->trackStock
            && (
                $command->trackLots
                || $command->trackRemnants
                || $command->allowNegativeStock
            )
        ) {
            throw ProductCreationException::inconsistentStockTracking();
        }

        // Construir primero la entidad valida sus datos locales.
        $product = Product::create(
            sku: $sku,
            name: $command->name,
            categoryId: $command->categoryId,
            baseUnitId: $command->baseUnitId,
            productType: $productType,
            barcode: $command->barcode,
            description: $command->description,
            minimumStock: $command->minimumStock,
            maximumStock: $command->maximumStock,
            reorderPoint: $command->reorderPoint,
            salePrice: $command->salePrice,
            trackStock: $command->trackStock,
            trackLots: $command->trackLots,
            trackRemnants: $command->trackRemnants,
            allowNegativeStock: $command->allowNegativeStock,
        );

        if ($this->products->existsBySku($sku)) {
            throw ProductCreationException::duplicateSku();
        }

        if (! $this->catalog->activeCategoryExists($command->categoryId)) {
            throw ProductCreationException::unavailableCategory();
        }

        if (! $this->catalog->activeUnitExists($command->baseUnitId)) {
            throw ProductCreationException::unavailableBaseUnit();
        }

        return $this->products->save($product);
    }
}
