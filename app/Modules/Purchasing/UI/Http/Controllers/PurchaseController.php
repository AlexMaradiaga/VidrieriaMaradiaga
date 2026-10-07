<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Application\Ports\PurchaseServiceInterface;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('purchases.view');

        return Inertia::render('Purchasing/Index', [
            'purchases' => DB::table('purchasing_documents as p')
                ->join('inventory_suppliers as s', 's.id', '=', 'p.supplier_id')
                ->orderByDesc('p.document_date')->orderByDesc('p.id')
                ->get(['p.id', 'p.number', 'p.supplier_document', 'p.document_date', 'p.due_date',
                    's.legal_name as supplier', 'p.payment_type', 'p.status', 'p.total', 'p.paid_amount']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('purchases.create');

        return Inertia::render('Purchasing/Create', [
            'suppliers' => DB::table('inventory_suppliers')->where('active', true)->whereNull('deleted_at')->orderBy('legal_name')->get(['id', 'code', 'legal_name', 'payment_terms_days']),
            'locations' => DB::table('inventory_locations as l')->join('inventory_warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->where('l.active', true)->whereNull('l.deleted_at')->orderBy('w.name')->orderBy('l.name')->get(['l.id', 'l.name', 'w.name as warehouse']),
            'products' => DB::table('inventory_products as p')->join('inventory_units as u', 'u.id', '=', 'p.base_unit_id')
                ->where('p.active', true)->where('p.track_stock', true)->whereNull('p.deleted_at')->orderBy('p.name')
                ->get(['p.id', 'p.sku', 'p.name', 'p.average_cost', 'p.base_unit_id as unit_id', 'u.symbol as unit']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function store(Request $request, PurchaseServiceInterface $purchases): RedirectResponse
    {
        Gate::authorize('purchases.create');
        Gate::authorize('purchases.post');
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'], 'supplier_id' => ['required', 'integer', 'exists:inventory_suppliers,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'supplier_document' => ['nullable', 'string', 'max:100'],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])],
            'treasury_account_id' => ['nullable', 'integer', 'exists:accounting_treasury_accounts,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'card', 'check', 'other'])],
            'paid_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['required', 'numeric', 'min:0'], 'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        try {
            $result = $purchases->create($data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['purchase' => $exception->getMessage()]);
        }
        return redirect()->route('purchases.index')->with('success', "Compra {$result['number']} registrada.");
    }

    public function payment(Request $request, int $purchaseId, PurchaseServiceInterface $purchases): RedirectResponse
    {
        Gate::authorize('purchases.payments.manage');
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_treasury_accounts,id'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'check', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $purchases->registerPayment($purchaseId, $data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        return back()->with('success', 'Pago a proveedor registrado.');
    }
}
