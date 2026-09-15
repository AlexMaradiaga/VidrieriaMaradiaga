<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Queries;

use App\Modules\Inventory\Application\Ports\InventoryDashboardQueryInterface;
use Illuminate\Support\Facades\DB;

final class SqlInventoryDashboardQuery implements InventoryDashboardQueryInterface
{
    public function get(): array
    {
        $products = DB::table('inventory_products')->whereNull('deleted_at');

        // Las existencias de un producto se suman antes de evaluar su mínimo.
        $balances = DB::table('inventory_stock_balances')
            ->select('product_id')
            ->selectRaw('SUM(quantity) AS quantity')
            ->groupBy('product_id');

        $critical = DB::table('inventory_products as p')
            ->leftJoinSub($balances, 'b', 'b.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->where('p.active', true)
            ->where('p.track_stock', true)
            ->whereRaw('COALESCE(b.quantity, 0) <= p.minimum_stock');

        // SQL Server mantiene la aritmética decimal. No convertir dinero a float.
        // Se valora cada saldo a costo promedio global, incluyendo productos
        // inactivos: desactivar un producto no elimina el material almacenado.
        $valuation = DB::table('inventory_stock_balances as b')
            ->join('inventory_products as p', 'p.id', '=', 'b.product_id')
            ->whereNull('p.deleted_at')
            ->selectRaw('
                CAST(COALESCE(SUM(
                    CAST(b.quantity AS DECIMAL(18, 4)) *
                    CAST(p.average_cost AS DECIMAL(18, 4))
                ), 0) AS VARCHAR(80)) AS inventory_value
            ')
            ->first();

        $alerts = (clone $critical)
            ->leftJoin('inventory_units as u', 'u.id', '=', 'p.base_unit_id')
            ->select(['p.id', 'p.sku', 'p.name', 'u.symbol as unit_symbol'])
            ->selectRaw('CAST(COALESCE(b.quantity, 0) AS VARCHAR(80)) AS quantity')
            ->selectRaw('CAST(p.minimum_stock AS VARCHAR(80)) AS minimum_stock')
            ->orderByRaw('COALESCE(b.quantity, 0) ASC')
            ->orderBy('p.id')
            ->limit(8)
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'unit_symbol' => (string) ($row->unit_symbol ?? ''),
                'quantity' => (string) $row->quantity,
                'minimum_stock' => (string) $row->minimum_stock,
            ])
            ->all();

        return [
            'inventory_value' => (string) ($valuation->inventory_value ?? '0'),
            'products_total' => (clone $products)->count(),
            'products_active' => (clone $products)->where('active', true)->count(),
            'pending_entries' => DB::table('inventory_movements')
                ->where('direction', 'inbound')
                ->where('status', 'draft')
                ->count(),
            'critical_products' => (clone $critical)->count(),
            'low_stock' => $alerts,
        ];
    }
}
