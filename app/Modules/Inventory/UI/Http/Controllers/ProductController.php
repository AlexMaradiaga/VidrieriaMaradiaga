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

final class ProductController extends Controller
{
    public function index(
        Request $request,
        ProductCatalogQueryInterface $catalog,
    ): Response {
        Gate::authorize('inventory.products.view');

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $page = (int) ($data['page'] ?? 1);

        $options = $request->user()->can('inventory.products.create')
            ? $catalog->formOptions()
            : ['categories' => [], 'units' => []];

        return Inertia::render('Inventory/Products/Index', [
            'products' => $catalog->search($search, $page),
            'filters' => ['search' => $search],
            'categories' => $options['categories'],
            'units' => $options['units'],
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
                'sku' => 'El SKU ya está registrado.',
            ]);
        } catch (ProductCreationException | InvalidProductException $exception) {
            throw ValidationException::withMessages([
                'product' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => [
                'id' => $product->id(),
                'sku' => $product->sku()->value(),
                'name' => $product->name(),
                'active' => $product->isActive(),
            ],
        ], 201);
    }
}
