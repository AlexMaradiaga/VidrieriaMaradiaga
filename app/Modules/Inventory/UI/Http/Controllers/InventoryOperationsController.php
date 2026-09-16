<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Ports\InventoryOperationsInterface;
use App\Modules\Inventory\Application\Ports\InventoryOperationsQueryInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryOperationsController extends Controller
{
    public function exitsCreate(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.exits.create');
        Gate::authorize('inventory.exits.post');

        return Inertia::render('Inventory/Operations/Create', [
            ...$query->options(),
            'mode' => 'exit',
        ]);
    }

    public function exitsStore(Request $request, InventoryOperationsInterface $operations): JsonResponse
    {
        Gate::authorize('inventory.exits.create');
        Gate::authorize('inventory.exits.post');
        $data = $request->validate($this->movementRules());

        try {
            $result = $operations->registerExit($data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => $result['repeated'] ? 'Esta salida ya había sido registrada.' : 'Salida confirmada correctamente.',
            'data' => $result,
        ], $result['repeated'] ? 200 : 201);
    }

    public function transfersCreate(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.transfers.create');

        return Inertia::render('Inventory/Operations/Create', [
            ...$query->options(),
            'mode' => 'transfer',
        ]);
    }

    public function transfersStore(Request $request, InventoryOperationsInterface $operations): JsonResponse
    {
        Gate::authorize('inventory.transfers.create');
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'min:1'],
            'source_location_id' => ['required', 'integer', 'min:1', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'min:1', 'different:source_location_id'],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
        ]);

        try {
            $result = $operations->transfer($data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => $result['repeated'] ? 'Este traslado ya había sido registrado.' : 'Traslado confirmado correctamente.',
            'data' => $result,
        ], $result['repeated'] ? 200 : 201);
    }

    public function counts(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.counts.view');

        return Inertia::render('Inventory/Counts/Index', $query->counts());
    }

    public function countsStore(Request $request, InventoryOperationsInterface $operations): JsonResponse
    {
        Gate::authorize('inventory.counts.create');
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'location_id' => ['required', 'integer', 'min:1'],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'min:1'],
            'lines.*.counted_quantity' => ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
        ]);

        try {
            $result = $operations->registerCount($data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => $result['repeated'] ? 'Este conteo ya había sido registrado.' : 'Conteo físico y ajustes confirmados.',
            'data' => $result,
        ], $result['repeated'] ? 200 : 201);
    }

    public function alerts(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.alerts.view');

        return Inertia::render('Inventory/Alerts/Index', $query->alerts());
    }

    public function remnants(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.remnants.view');

        return Inertia::render('Inventory/Remnants/Index', $query->remnants());
    }

    public function remnantsStore(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.remnants.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:inventory_remnants,code'],
            'product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'width_mm' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'height_mm' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'thickness_mm' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('inventory_remnants')->insert([
            ...$data,
            'status' => 'available',
            'created_by' => (int) $request->user()->getAuthIdentifier(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Retazo registrado correctamente.');
    }

    public function remnantsStatus(Request $request, int $remnantId): RedirectResponse
    {
        Gate::authorize('inventory.remnants.manage');
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'reserved', 'consumed', 'discarded'])]]);
        DB::table('inventory_remnants')->where('id', $remnantId)->update([...$data, 'updated_at' => now()]);

        return back()->with('success', 'Estado del retazo actualizado.');
    }

    public function kits(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.kits.view');

        return Inertia::render('Inventory/Kits/Index', $query->kits());
    }

    public function kitsStore(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.kits.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:inventory_kits,code'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:inventory_products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $now = now();
            $kitId = DB::table('inventory_kits')->insertGetId([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => true,
                'created_by' => (int) $request->user()->getAuthIdentifier(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('inventory_kit_items')->insert(array_map(fn (array $item): array => [
                'kit_id' => $kitId,
                'product_id' => (int) $item['product_id'],
                'quantity' => number_format((float) $item['quantity'], 4, '.', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ], $data['items']));
        });

        return back()->with('success', 'Kit registrado correctamente.');
    }

    public function kitsActive(Request $request, int $kitId): RedirectResponse
    {
        Gate::authorize('inventory.kits.manage');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::table('inventory_kits')->where('id', $kitId)->update([...$data, 'updated_at' => now()]);

        return back()->with('success', 'Estado del kit actualizado.');
    }

    public function catalogs(InventoryOperationsQueryInterface $query): Response
    {
        Gate::authorize('inventory.catalogs.view');

        return Inertia::render('Inventory/Catalogs/Index', $query->catalogs());
    }

    public function catalogStore(Request $request, string $catalog): RedirectResponse
    {
        Gate::authorize('inventory.catalogs.manage');
        [$table, $data] = $this->catalogData($request, $catalog);
        DB::table($table)->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Registro creado correctamente.');
    }

    public function catalogActive(Request $request, string $catalog, int $recordId): RedirectResponse
    {
        Gate::authorize('inventory.catalogs.manage');
        $tables = [
            'units' => 'inventory_units', 'categories' => 'inventory_categories',
            'suppliers' => 'inventory_suppliers', 'warehouses' => 'inventory_warehouses',
            'locations' => 'inventory_locations',
            'product-units' => 'inventory_product_units',
        ];
        abort_unless(isset($tables[$catalog]), 404);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::table($tables[$catalog])->where('id', $recordId)->update([...$data, 'updated_at' => now()]);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function cutCalculator(): Response
    {
        Gate::authorize('inventory.cuts.use');

        return Inertia::render('Inventory/CutCalculator/Index');
    }

    private function movementRules(): array
    {
        return [
            'operation_key' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
            'unit_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['sale', 'work_order', 'production', 'internal_consumption', 'waste', 'adjustment'])],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'required_if:reason,sale,work_order,production', 'string', 'max:100'],
            'notes' => ['nullable', 'required_if:reason,waste,adjustment', 'string', 'max:1000'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
        ];
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function catalogData(Request $request, string $catalog): array
    {
        return match ($catalog) {
            'units' => ['inventory_units', $request->validate([
                'code' => ['required', 'string', 'max:20', 'unique:inventory_units,code'],
                'name' => ['required', 'string', 'max:80'], 'symbol' => ['required', 'string', 'max:20'],
                'dimension' => ['required', Rule::in(['quantity', 'length', 'area', 'weight', 'volume'])],
                'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            ])],
            'categories' => ['inventory_categories', $request->validate([
                'code' => ['required', 'string', 'max:30', 'unique:inventory_categories,code'],
                'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:500'],
                'sort_order' => ['required', 'integer', 'min:0'], 'parent_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            ])],
            'suppliers' => ['inventory_suppliers', $request->validate([
                'code' => ['required', 'string', 'max:30', 'unique:inventory_suppliers,code'],
                'legal_name' => ['required', 'string', 'max:150'], 'trade_name' => ['nullable', 'string', 'max:150'],
                'tax_id' => ['nullable', 'string', 'max:30'], 'contact_name' => ['nullable', 'string', 'max:120'],
                'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string', 'max:500'], 'payment_terms_days' => ['required', 'integer', 'min:0'],
            ])],
            'warehouses' => ['inventory_warehouses', $request->validate([
                'code' => ['required', 'string', 'max:30', 'unique:inventory_warehouses,code'],
                'name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:30'], 'is_default' => ['required', 'boolean'],
            ])],
            'locations' => ['inventory_locations', $request->validate([
                'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
                'code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:120'],
                'zone' => ['nullable', 'string', 'max:50'], 'aisle' => ['nullable', 'string', 'max:30'],
                'rack' => ['nullable', 'string', 'max:30'], 'bin' => ['nullable', 'string', 'max:30'],
            ])],
            'product-units' => ['inventory_product_units', $request->validate([
                'product_id' => ['required', 'integer', 'exists:inventory_products,id'],
                'unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
                'conversion_factor_to_base' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
                'is_purchase_unit' => ['required', 'boolean'],
                'is_sale_unit' => ['required', 'boolean'],
                'is_issue_unit' => ['required', 'boolean'],
            ])],
            default => abort(404),
        };
    }
}
