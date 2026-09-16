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
            'sku' => __('inventory.attributes.sku'),
            'name' => __('inventory.attributes.name'),
            'category_id' => __('inventory.attributes.category_id'),
            'base_unit_id' => __('inventory.attributes.base_unit_id'),
            'product_type' => __('inventory.attributes.product_type'),
            'barcode' => __('inventory.attributes.barcode'),
            'description' => __('inventory.attributes.description'),
            'minimum_stock' => __('inventory.attributes.minimum_stock'),
            'maximum_stock' => __('inventory.attributes.maximum_stock'),
            'reorder_point' => __('inventory.attributes.reorder_point'),
            'sale_price' => __('inventory.attributes.sale_price'),
        ];
    }
}
