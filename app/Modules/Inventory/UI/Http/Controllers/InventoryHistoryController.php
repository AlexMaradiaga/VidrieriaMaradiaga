<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Ports\InventoryMovementQueryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryHistoryController extends Controller
{
    public function index(
        Request $request,
        InventoryMovementQueryInterface $query,
    ): Response {
        Gate::authorize('inventory.entries.view');

        $filters = $this->filters($request, false);

        return Inertia::render('Inventory/History', [
            'mode' => 'entries',
            'filters' => $filters,
            'options' => $query->options(),
            'entries' => $query->entries($filters),
            'entry' => null,
            'ledger' => null,
        ]);
    }

    public function show(
        Request $request,
        InventoryMovementQueryInterface $query,
        int $movementId,
    ): Response {
        Gate::authorize('inventory.entries.view');

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $entry = $query->entry(
            $movementId,
            (int) ($validated['page'] ?? 1)
        );

        abort_if($entry === null, 404);

        return Inertia::render('Inventory/History', [
            'mode' => 'detail',
            'filters' => null,
            'options' => null,
            'entries' => null,
            'entry' => $entry,
            'ledger' => null,
        ]);
    }

    public function kardex(
        Request $request,
        InventoryMovementQueryInterface $query,
    ): Response {
        Gate::authorize('inventory.kardex.view');

        $filters = $this->filters($request, true);

        $ledger = $filters['product_id'] === null
            ? null
            : $query->kardex($filters);

        abort_if(
            $filters['product_id'] !== null && $ledger === null,
            404
        );

        return Inertia::render('Inventory/History', [
            'mode' => 'kardex',
            'filters' => $filters,
            'options' => $query->options(),
            'entries' => null,
            'entry' => null,
            'ledger' => $ledger,
        ]);
    }

    private function filters(Request $request, bool $kardex): array
    {
        $rules = [
            'from' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:9998-12-31',
            ],
            'to' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:9998-12-31',
            ],
            'product_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('inventory_products', 'id'),
            ],
            'location_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('inventory_locations', 'id'),
            ],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];

        if ($request->filled('from')) {
            $rules['to'][] = 'after_or_equal:from';
        }

        if (! $kardex) {
            $rules['search'] = ['nullable', 'string', 'max:100'];
            $rules['status'] = [
                'nullable',
                Rule::in(['draft', 'posted', 'cancelled']),
            ];
            $rules['reason'] = [
                'nullable',
                Rule::in(['purchase', 'initial_balance']),
            ];
            $rules['supplier_id'] = [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('inventory_suppliers', 'id'),
            ];
        }

        $data = $request->validate($rules, [
            'to.after_or_equal' =>
                'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        $data = array_replace([
            'from' => null,
            'to' => null,
            'product_id' => null,
            'location_id' => null,
            'page' => 1,
        ], $data);

        if (! $kardex) {
            $data = array_replace([
                'search' => '',
                'status' => '',
                'reason' => '',
                'supplier_id' => null,
            ], $data);
        }

        foreach (['product_id', 'location_id', 'supplier_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = isset($data[$key])
                    ? (int) $data[$key]
                    : null;
            }
        }

        $data['page'] = (int) ($data['page'] ?? 1);

        if (! $kardex) {
            $data['search'] = trim((string) ($data['search'] ?? ''));
        }

        return $data;
    }
}
