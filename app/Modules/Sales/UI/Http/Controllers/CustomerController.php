<?php

declare(strict_types=1);

namespace App\Modules\Sales\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('sales.customers.manage');
        return Inertia::render('Sales/Customers', ['customers' => DB::table('sales_customers')->whereNull('deleted_at')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('sales.customers.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:sales_customers,code'], 'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'], 'tax_id' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'], 'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        DB::table('sales_customers')->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function active(Request $request, int $customerId): RedirectResponse
    {
        Gate::authorize('sales.customers.manage');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::table('sales_customers')->where('id', $customerId)->update(['active' => $data['active'], 'updated_at' => now()]);
        return back()->with('success', 'Estado del cliente actualizado.');
    }
}
