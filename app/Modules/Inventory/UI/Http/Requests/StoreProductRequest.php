<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Requests;

use App\Modules\Inventory\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.products.create') ?? false;
    }

    public function rules(): array
    {
        $decimal = ['regex:/^\d{1,14}(?:\.\d{1,4})?$/'];

        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['required', 'integer', 'min:1'],
            'base_unit_id' => ['required', 'integer', 'min:1'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'barcode' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'minimum_stock' => ['required', ...$decimal],
            'maximum_stock' => ['nullable', ...$decimal],
            'reorder_point' => ['required', ...$decimal],
            'sale_price' => ['required', ...$decimal],
            'track_stock' => ['required', 'boolean'],
            'track_lots' => ['required', 'boolean'],
            'track_remnants' => ['required', 'boolean'],
            'allow_negative_stock' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'name' => 'nombre',
            'category_id' => 'categoría',
            'base_unit_id' => 'unidad base',
            'product_type' => 'tipo de producto',
            'barcode' => 'código de barras',
            'description' => 'descripción',
            'minimum_stock' => 'stock mínimo',
            'maximum_stock' => 'stock máximo',
            'reorder_point' => 'punto de reorden',
            'sale_price' => 'precio de venta',
        ];
    }
}
