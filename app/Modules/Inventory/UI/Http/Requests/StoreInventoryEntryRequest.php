<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('inventory.entries.create') ?? false)
            && ($this->user()?->can('inventory.entries.post') ?? false);
    }

    public function rules(): array
    {
        return [
            'operation_key' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
            'unit_id' => ['required', 'integer', 'min:1'],

            'reason' => [
                'required',
                Rule::in(['purchase', 'initial_balance']),
            ],

            'supplier_id' => [
                'nullable',
                'required_if:reason,purchase',
                'integer',
                'min:1',
            ],

            'document_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],

            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'quantity' => [
                'required',
                'string',
                'regex:/^\d{1,14}(?:\.\d{1,4})?$/',
            ],

            'unit_cost' => [
                'required',
                'string',
                'regex:/^\d{1,14}(?:\.\d{1,4})?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'Selecciona un proveedor para una compra.',
            'regex' => 'El campo :attribute debe usar punto y hasta cuatro decimales.',
            'string' => 'El campo :attribute debe enviarse como texto.',
            'date_format' => 'La fecha debe tener formato año-mes-día.',
            'before_or_equal' => 'La fecha no puede estar en el futuro.',
            'uuid' => 'El identificador de solicitud no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'location_id' => 'ubicación',
            'unit_id' => 'unidad',
            'supplier_id' => 'proveedor',
            'quantity' => 'cantidad',
            'unit_cost' => 'costo unitario',
            'document_date' => 'fecha del documento',
            'reason' => 'motivo',
        ];
    }
}
