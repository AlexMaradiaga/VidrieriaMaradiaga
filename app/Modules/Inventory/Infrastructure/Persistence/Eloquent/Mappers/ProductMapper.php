<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Enums\ProductType;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryProductModel;

final class ProductMapper
{
    public function toDomain(InventoryProductModel $model): Product
    {
        return Product::reconstitute(
            id: $model->id,
            categoryId: $model->category_id,
            baseUnitId: $model->base_unit_id,
            sku: Sku::fromString($model->sku),
            barcode: $model->barcode,
            name: $model->name,
            description: $model->description,
            productType: ProductType::from($model->product_type),
            minimumStock: $model->minimum_stock,
            maximumStock: $model->maximum_stock,
            reorderPoint: $model->reorder_point,
            averageCost: $model->average_cost,
            lastPurchaseCost: $model->last_purchase_cost,
            salePrice: $model->sale_price,
            trackStock: $model->track_stock,
            trackLots: $model->track_lots,
            trackRemnants: $model->track_remnants,
            allowNegativeStock: $model->allow_negative_stock,
            active: $model->active,
        );
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toPersistence(Product $product): array
    {
        return [
            'category_id' => $product->categoryId(),
            'base_unit_id' => $product->baseUnitId(),
            'sku' => $product->sku()->value(),
            'barcode' => $product->barcode(),
            'name' => $product->name(),
            'description' => $product->description(),
            'product_type' => $product->productType()->value,
            'minimum_stock' => $product->minimumStock(),
            'maximum_stock' => $product->maximumStock(),
            'reorder_point' => $product->reorderPoint(),
            'average_cost' => $product->averageCost(),
            'last_purchase_cost' => $product->lastPurchaseCost(),
            'sale_price' => $product->salePrice(),
            'track_stock' => $product->tracksStock(),
            'track_lots' => $product->tracksLots(),
            'track_remnants' => $product->tracksRemnants(),
            'allow_negative_stock' => $product->allowsNegativeStock(),
            'active' => $product->isActive(),
        ];
    }
}
