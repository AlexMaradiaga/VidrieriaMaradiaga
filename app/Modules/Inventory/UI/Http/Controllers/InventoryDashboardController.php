<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Ports\InventoryDashboardQueryInterface;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryDashboardController extends Controller
{
    public function __invoke(InventoryDashboardQueryInterface $dashboard): Response
    {
        // El inicio sigue disponible para futuros usuarios de otros módulos.
        // Los datos de inventario solo se consultan y envían con permiso.
        return Inertia::render('Dashboard/Index', [
            'dashboard' => Gate::allows('inventory.products.view')
                ? $dashboard->get()
                : null,
        ]);
    }
}
