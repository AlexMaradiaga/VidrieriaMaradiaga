<?php

declare(strict_types=1);

namespace App\Modules\Sales\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Application\Ports\SalesServiceInterface;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SalesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('sales.view');
        return Inertia::render('Sales/Index', [
            'sales' => DB::table('sales_documents as s')->join('sales_customers as c', 'c.id', '=', 's.customer_id')
                ->orderByDesc('s.document_date')->orderByDesc('s.id')
                ->get(['s.id', 's.number', 's.document_date', 'c.name as customer', 's.payment_type', 's.status', 's.total', 's.paid_amount']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('sales.create');
        return Inertia::render('Sales/Create', [
            'customers' => DB::table('sales_customers')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => DB::table('inventory_locations as l')->join('inventory_warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->where('l.active', true)->orderBy('w.name')->orderBy('l.name')->get(['l.id', 'l.name', 'w.name as warehouse']),
            'products' => DB::table('inventory_products as p')->join('inventory_units as u', 'u.id', '=', 'p.base_unit_id')
                ->where('p.active', true)->orderBy('p.name')->get(['p.id', 'p.sku', 'p.name', 'p.sale_price', 'p.base_unit_id as unit_id', 'u.symbol as unit']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function store(Request $request, SalesServiceInterface $sales): RedirectResponse
    {
        Gate::authorize('sales.create');
        Gate::authorize('sales.post');
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'], 'customer_id' => ['required', 'integer', 'exists:sales_customers,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])], 'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'card', 'other'])],
            'treasury_account_id' => ['nullable', 'integer', 'exists:accounting_treasury_accounts,id'],
            'paid_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['required', 'numeric', 'min:0'], 'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        try {
            $result = $sales->create($data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['sale' => $exception->getMessage()]);
        }
        return redirect()->route('sales.index')->with('success', "Venta {$result['number']} registrada.");
    }

    public function payment(Request $request, int $saleId, SalesServiceInterface $sales): RedirectResponse
    {
        Gate::authorize('sales.payments.manage');
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'other'])],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_treasury_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $sales->registerPayment($saleId, $data, (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        return back()->with('success', 'Cobro registrado correctamente.');
    }
}
