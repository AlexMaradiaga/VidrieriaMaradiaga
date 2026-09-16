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
            'required' => __('inventory.validation.required'),
            'required_if' => __('inventory.validation.supplier_required'),
            'regex' => __('inventory.validation.decimal'),
            'string' => __('inventory.validation.string'),
            'date_format' => __('inventory.validation.date_format'),
            'before_or_equal' => __('inventory.validation.not_future'),
            'uuid' => __('inventory.validation.uuid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => __('inventory.attributes.product_id'),
            'location_id' => __('inventory.attributes.location_id'),
            'unit_id' => __('inventory.attributes.unit_id'),
            'supplier_id' => __('inventory.attributes.supplier_id'),
            'quantity' => __('inventory.attributes.quantity'),
            'unit_cost' => __('inventory.attributes.unit_cost'),
            'document_date' => __('inventory.attributes.document_date'),
            'reason' => __('inventory.attributes.reason'),
        ];
    }
}
