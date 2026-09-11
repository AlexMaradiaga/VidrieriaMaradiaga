<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Mappers\ProductMapper;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Models\InventoryProductModel;
use LogicException;
use RuntimeException;

final class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly ProductMapper $mapper,
    ) {
    }

    public function findById(int $id): ?Product
    {
        $model = InventoryProductModel::query()->find($id);

        return $model === null
            ? null
            : $this->mapper->toDomain($model);
    }

    public function findBySku(Sku $sku): ?Product
    {
        $model = InventoryProductModel::query()
            ->where('sku', $sku->value())
            ->first();

        return $model === null
            ? null
            : $this->mapper->toDomain($model);
    }

    public function existsBySku(
        Sku $sku,
        ?int $excludingProductId = null,
    ): bool {
        // La restricción UNIQUE también incluye productos eliminados.
        $query = InventoryProductModel::withTrashed()
            ->where('sku', $sku->value());

        if ($excludingProductId !== null) {
            $query->where('id', '<>', $excludingProductId);
        }

        return $query->exists();
    }

    public function save(Product $product): Product
    {
        $model = $product->id() === null
            ? new InventoryProductModel()
            : InventoryProductModel::query()->findOrFail($product->id());

        $model->fill($this->mapper->toPersistence($product));

        if (! $model->save()) {
            throw new RuntimeException('No se pudo guardar el producto.');
        }

        // Devuelve una nueva entidad con el ID asignado por SQL Server.
        return $this->mapper->toDomain($model);
    }

    public function delete(Product $product): void
    {
        if ($product->id() === null) {
            throw new LogicException(
                'No se puede eliminar un producto que no ha sido guardado.'
            );
        }

        $model = InventoryProductModel::query()
            ->findOrFail($product->id());

        if (! $model->delete()) {
            throw new RuntimeException('No se pudo eliminar el producto.');
        }
    }

    public function updateCatalogDetails(Product $product): Product
    {
        if ($product->id() === null) {
            throw new LogicException('El producto debe estar guardado.');
        }

        $model = InventoryProductModel::query()
            ->findOrFail($product->id());

        // Actualizar únicamente los campos editables del catálogo.
        $model->fill([
            'name' => $product->name(),
            'barcode' => $product->barcode(),
            'description' => $product->description(),
            'minimum_stock' => $product->minimumStock(),
            'maximum_stock' => $product->maximumStock(),
            'reorder_point' => $product->reorderPoint(),
            'sale_price' => $product->salePrice(),
        ]);

        if (! $model->save()) {
            throw new RuntimeException('No se pudo actualizar el producto.');
        }

        return $this->mapper->toDomain($model);
    }

    public function updateActiveStatus(Product $product): Product
    {
        if ($product->id() === null) {
            throw new LogicException('El producto debe estar guardado.');
        }

        $model = InventoryProductModel::query()
            ->findOrFail($product->id());

        $model->active = $product->isActive();

        if (! $model->save()) {
            throw new RuntimeException('No se pudo actualizar el estado.');
        }

        return $this->mapper->toDomain($model);
    }
}
