<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.products.update') ?? false;
    }

    public function rules(): array
    {
        $decimal = ['regex:/^\d{1,14}(?:\.\d{1,4})?$/'];

        return [
            'name' => ['required', 'string', 'max:180'],
            'barcode' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'minimum_stock' => ['required', ...$decimal],
            'maximum_stock' => ['nullable', ...$decimal],
            'reorder_point' => ['required', ...$decimal],
            'sale_price' => ['required', ...$decimal],

            'sku' => ['prohibited'],
            'category_id' => ['prohibited'],
            'base_unit_id' => ['prohibited'],
            'product_type' => ['prohibited'],
            'average_cost' => ['prohibited'],
            'last_purchase_cost' => ['prohibited'],
            'active' => ['prohibited'],
            'track_stock' => ['prohibited'],
            'track_lots' => ['prohibited'],
            'track_remnants' => ['prohibited'],
            'allow_negative_stock' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'regex' => 'El campo :attribute debe usar punto decimal y hasta cuatro decimales.',
            'max' => 'El campo :attribute supera la longitud permitida.',
            'prohibited' => 'El campo :attribute no se puede modificar en esta operación.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'barcode' => 'código de barras',
            'description' => 'descripción',
            'minimum_stock' => 'existencia mínima',
            'maximum_stock' => 'existencia máxima',
            'reorder_point' => 'cantidad para solicitar reposición',
            'sale_price' => 'precio de venta',
        ];
    }
}
