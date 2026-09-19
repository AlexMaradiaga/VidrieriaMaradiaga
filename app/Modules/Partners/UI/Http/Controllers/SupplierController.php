<?php

declare(strict_types=1);

namespace App\Modules\Partners\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('suppliers.view');
        return Inertia::render('Partners/Suppliers', [
            'suppliers' => DB::table('inventory_suppliers')->whereNull('deleted_at')->orderBy('legal_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('suppliers.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:inventory_suppliers,code'],
            'legal_name' => ['required', 'string', 'max:150'], 'trade_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:30'], 'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'], 'payment_terms_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        DB::table('inventory_suppliers')->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Proveedor creado correctamente.');
    }

    public function active(Request $request, int $supplierId): RedirectResponse
    {
        Gate::authorize('suppliers.manage');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::table('inventory_suppliers')->where('id', $supplierId)->update(['active' => $data['active'], 'updated_at' => now()]);
        return back()->with('success', 'Estado del proveedor actualizado.');
    }
}
