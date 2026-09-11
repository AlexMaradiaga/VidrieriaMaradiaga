<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Handlers;

use App\Modules\Inventory\Application\Commands\UpdateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;

final class ManageProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function update(UpdateProductCommand $command): Product
    {
        $product = $this->products->findById($command->productId);

        if ($product === null) {
            throw new ProductNotFoundException();
        }

        $product->updateInformation(
            sku: $product->sku(),
            name: $command->name,
            categoryId: $product->categoryId(),
            baseUnitId: $product->baseUnitId(),
            productType: $product->productType(),
            barcode: $command->barcode,
            description: $command->description,
        );

        $product->configureInventory(
            minimumStock: $command->minimumStock,
            maximumStock: $command->maximumStock,
            reorderPoint: $command->reorderPoint,
            trackStock: $product->tracksStock(),
            trackLots: $product->tracksLots(),
            trackRemnants: $product->tracksRemnants(),
            allowNegativeStock: $product->allowsNegativeStock(),
        );

        $product->changeCommercialValues(
            averageCost: $product->averageCost(),
            lastPurchaseCost: $product->lastPurchaseCost(),
            salePrice: $command->salePrice,
        );

        return $this->products->updateCatalogDetails($product);
    }

    public function setActive(int $productId, bool $active): Product
    {
        $product = $this->products->findById($productId);

        if ($product === null) {
            throw new ProductNotFoundException();
        }

        if ($active) {
            $product->activate();
        } else {
            $product->deactivate();
        }

        return $this->products->updateActiveStatus($product);
    }
}
