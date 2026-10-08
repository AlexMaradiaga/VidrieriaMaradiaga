<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Commands\CreateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductCreationException;
use App\Modules\Inventory\Application\Handlers\CreateProductHandler;
use App\Modules\Inventory\Domain\Exceptions\InvalidProductException;
use App\Modules\Inventory\UI\Http\Requests\StoreProductRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Modules\Inventory\Application\Ports\ProductCatalogQueryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Modules\Inventory\Application\Commands\UpdateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Application\Handlers\ManageProductHandler;
use App\Modules\Inventory\UI\Http\Requests\UpdateProductRequest;

final class ProductController extends Controller
{
    public function index(
        Request $request,
        ProductCatalogQueryInterface $catalog,
    ): Response {
        Gate::authorize('inventory.products.view');

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort' => ['nullable', 'in:sku_asc,sku_desc,name_asc,name_desc,category_asc'],
            'per_page' => ['nullable', 'integer', 'in:15,30,50,100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $status = (string) ($data['status'] ?? 'all');
        $sort = (string) ($data['sort'] ?? 'sku_asc');
        $perPage = (int) ($data['per_page'] ?? 30);
        $page = (int) ($data['page'] ?? 1);

        $options = $catalog->formOptions();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $catalog->search(
                $search,
                $categoryId,
                $status,
                $sort,
                $perPage,
                $page,
            ),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'status' => $status,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
            'categories' => $options['categories'],
            'units' => $request->user()->can('inventory.products.create')
                ? $options['units']
                : [],
        ]);
    }

    public function store(
        StoreProductRequest $request,
        CreateProductHandler $handler,
    ): JsonResponse {
        $data = $request->validated();

        $command = new CreateProductCommand(
            sku: $data['sku'],
            name: $data['name'],
            categoryId: (int) $data['category_id'],
            baseUnitId: (int) $data['base_unit_id'],
            productType: $data['product_type'],
            barcode: $data['barcode'] ?? null,
            description: $data['description'] ?? null,
            minimumStock: (string) $data['minimum_stock'],
            maximumStock: isset($data['maximum_stock'])
                ? (string) $data['maximum_stock']
                : null,
            reorderPoint: (string) $data['reorder_point'],
            salePrice: (string) $data['sale_price'],
            trackStock: (bool) $data['track_stock'],
            trackLots: (bool) $data['track_lots'],
            trackRemnants: (bool) $data['track_remnants'],
            allowNegativeStock: (bool) $data['allow_negative_stock'],
        );

        try {
            $product = $handler->handle($command);
        } catch (UniqueConstraintViolationException $exception) {
            // La única restricción UNIQUE del producto, además de su ID
            // autogenerado, es actualmente el SKU.
            throw ValidationException::withMessages([
                'sku' => __('inventory.errors.duplicate_sku'),
            ]);
        } catch (ProductCreationException | InvalidProductException $exception) {
            throw ValidationException::withMessages([
                'product' => $this->localizedProductError($exception->getMessage()),
            ]);
        }

        return response()->json([
            'message' => __('inventory.responses.product_created'),
            'data' => [
                'id' => $product->id(),
                'sku' => $product->sku()->value(),
                'name' => $product->name(),
                'active' => $product->isActive(),
            ],
        ], 201);
    }

    public function update(
        UpdateProductRequest $request,
        ManageProductHandler $handler,
        int $productId,
    ): JsonResponse {
        $data = $request->validated();

        try {
            $product = $handler->update(
                new UpdateProductCommand(
                    productId: $productId,
                    name: $data['name'],
                    barcode: $data['barcode'] ?? null,
                    description: $data['description'] ?? null,
                    minimumStock: (string) $data['minimum_stock'],
                    maximumStock: isset($data['maximum_stock'])
                        ? (string) $data['maximum_stock']
                        : null,
                    reorderPoint: (string) $data['reorder_point'],
                    salePrice: (string) $data['sale_price'],
                )
            );
        } catch (ProductNotFoundException $exception) {
            abort(404, __('inventory.errors.product_not_found'));
        } catch (InvalidProductException $exception) {
            throw ValidationException::withMessages([
                'product' => $this->localizedProductError($exception->getMessage()),
            ]);
        }

        return response()->json([
            'message' => __('inventory.responses.product_updated'),
            'data' => ['id' => $product->id()],
        ]);
    }

    public function setActive(
        Request $request,
        ManageProductHandler $handler,
        int $productId,
    ): JsonResponse {
        Gate::authorize('inventory.products.toggle-active');

        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        try {
            $product = $handler->setActive(
                $productId,
                (bool) $data['active'],
            );
        } catch (ProductNotFoundException $exception) {
            abort(404, __('inventory.errors.product_not_found'));
        }

        return response()->json([
            'message' => $product->isActive()
                ? __('inventory.responses.product_activated')
                : __('inventory.responses.product_deactivated'),
            'data' => [
                'id' => $product->id(),
                'active' => $product->isActive(),
            ],
        ]);
    }

    private function localizedProductError(string $message): string
    {
        $messages = [
            'El SKU ya está registrado, incluso si el producto fue eliminado.' => 'duplicate_sku_deleted',
            'La categoría no existe o está inactiva.' => 'category_unavailable',
            'La unidad base no existe o está inactiva.' => 'base_unit_unavailable',
            'El tipo de producto no es válido.' => 'invalid_product_type',
            'Para controlar lotes, retazos o existencias negativas, debes activar el control de stock.' => 'stock_tracking_required',
            'El producto no existe o fue eliminado.' => 'product_not_found',
            'El código SKU del producto es obligatorio.' => 'empty_sku',
            'El código SKU solamente puede contener letras, números, puntos, guiones y guiones bajos.' => 'invalid_sku_format',
            'El nombre del producto es obligatorio.' => 'empty_name',
            'El nombre del producto no puede superar los 180 caracteres.' => 'name_too_long',
            'El código de barras no puede superar los 80 caracteres.' => 'barcode_too_long',
            'La descripción no puede superar los 1000 caracteres.' => 'description_too_long',
            'El stock máximo no puede ser menor que el stock mínimo.' => 'maximum_below_minimum',
        ];

        $key = $messages[$message] ?? null;

        return $key === null ? $message : __('inventory.errors.'.$key);
    }
}
