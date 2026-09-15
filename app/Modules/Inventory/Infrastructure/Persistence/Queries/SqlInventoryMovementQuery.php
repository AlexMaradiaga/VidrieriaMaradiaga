<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Queries;

use App\Modules\Inventory\Application\Ports\InventoryMovementQueryInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SqlInventoryMovementQuery implements InventoryMovementQueryInterface
{
    public function options(): array
    {
        // Incluye registros inactivos o eliminados lógicamente
        // para conservar el acceso a su historia.
        return [
            'products' => DB::table('inventory_products')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'sku', 'name'])
                ->map(static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => $row->sku.' — '.$row->name,
                ])
                ->all(),

            'locations' => DB::table('inventory_locations as l')
                ->join(
                    'inventory_warehouses as w',
                    'w.id',
                    '=',
                    'l.warehouse_id'
                )
                ->orderBy('w.name')
                ->orderBy('l.name')
                ->orderBy('l.id')
                ->get(['l.id', 'l.name', 'w.name as warehouse'])
                ->map(static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => $row->warehouse.' / '.$row->name,
                ])
                ->all(),

            'suppliers' => DB::table('inventory_suppliers')
                ->orderBy('legal_name')
                ->orderBy('id')
                ->get(['id', 'legal_name'])
                ->map(static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => $row->legal_name,
                ])
                ->all(),
        ];
    }

    public function entries(array $filters): array
    {
        $totals = DB::table('inventory_movement_lines')
            ->select('movement_id')
            ->selectRaw(
                'SUM(total_cost) AS total_cost, COUNT(*) AS line_count'
            )
            ->groupBy('movement_id');

        $query = DB::table('inventory_movements as m')
            ->leftJoinSub(
                $totals,
                't',
                't.movement_id',
                '=',
                'm.id'
            )
            ->leftJoin(
                'inventory_suppliers as s',
                's.id',
                '=',
                'm.supplier_id'
            )
            ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
            ->where('m.direction', 'inbound');

        foreach (['status', 'reason', 'supplier_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where('m.'.$key, $filters[$key]);
            }
        }

        $this->dates($query, 'm.document_date', $filters);

        // EXISTS evita duplicar documentos que tienen varias líneas.
        if (
            ! empty($filters['product_id'])
            || ! empty($filters['location_id'])
        ) {
            $query->whereExists(
                function (Builder $lines) use ($filters): void {
                    $lines
                        ->selectRaw('1')
                        ->from('inventory_movement_lines as f')
                        ->whereColumn('f.movement_id', 'm.id');

                    foreach (['product_id', 'location_id'] as $key) {
                        if (! empty($filters[$key])) {
                            $lines->where('f.'.$key, $filters[$key]);
                        }
                    }
                }
            );
        }

        if (($filters['search'] ?? '') !== '') {
            $escaped = str_replace(
                ['[', '%', '_'],
                ['[[]', '[%]', '[_]'],
                $filters['search']
            );

            $pattern = '%'.$escaped.'%';

            $query->where(function (Builder $search) use ($pattern): void {
                $search
                    ->where('m.reference', 'like', $pattern)
                    ->orWhereExists(
                        function (Builder $lines) use ($pattern): void {
                            $lines
                                ->selectRaw('1')
                                ->from('inventory_movement_lines as f')
                                ->join(
                                    'inventory_products as p',
                                    'p.id',
                                    '=',
                                    'f.product_id'
                                )
                                ->whereColumn('f.movement_id', 'm.id')
                                ->where(function (Builder $product) use ($pattern): void {
                                    $product
                                        ->where('p.sku', 'like', $pattern)
                                        ->orWhere('p.name', 'like', $pattern);
                                });
                        }
                    );
            });
        }

        $query
            ->select([
                'm.id',
                'm.document_date',
                'm.reference',
                'm.reason',
                'm.status',
                'm.posted_at',
                's.legal_name as supplier',
                'u.name as creator',
            ])
            ->selectRaw(
                'CAST(COALESCE(t.total_cost, 0) AS VARCHAR(80)) AS total_cost,
                 COALESCE(t.line_count, 0) AS line_count'
            )
            ->orderByDesc('m.document_date')
            ->orderByDesc('m.id');

        return $this->page($query, (int) ($filters['page'] ?? 1));
    }

    public function entry(int $movementId, int $page = 1): ?array
    {
        $header = DB::table('inventory_movements as m')
            ->leftJoin(
                'inventory_suppliers as s',
                's.id',
                '=',
                'm.supplier_id'
            )
            ->leftJoin('users as c', 'c.id', '=', 'm.created_by')
            ->leftJoin('users as p', 'p.id', '=', 'm.posted_by')
            ->leftJoin('users as x', 'x.id', '=', 'm.cancelled_by')
            ->where('m.id', $movementId)
            ->where('m.direction', 'inbound')
            ->first([
                'm.id',
                'm.document_date',
                'm.reference',
                'm.reason',
                'm.status',
                'm.notes',
                'm.created_at',
                'm.posted_at',
                'm.cancelled_at',
                'm.cancellation_reason',
                's.legal_name as supplier',
                'c.name as creator',
                'p.name as poster',
                'x.name as canceller',
            ]);

        if ($header === null) {
            return null;
        }

        $total = DB::table('inventory_movement_lines')
            ->where('movement_id', $movementId)
            ->selectRaw(
                'CAST(COALESCE(SUM(total_cost), 0) AS VARCHAR(80)) AS total'
            )
            ->first();

        $lines = DB::table('inventory_movement_lines as l')
            ->join('inventory_products as p', 'p.id', '=', 'l.product_id')
            ->join('inventory_locations as loc', 'loc.id', '=', 'l.location_id')
            ->join(
                'inventory_warehouses as w',
                'w.id',
                '=',
                'loc.warehouse_id'
            )
            ->join('inventory_units as u', 'u.id', '=', 'l.unit_id')
            ->join('inventory_units as b', 'b.id', '=', 'l.base_unit_id')
            ->where('l.movement_id', $movementId)
            ->select([
                'l.id',
                'l.line_number',
                'l.product_id',
                'p.sku',
                'p.name as product',
                'w.name as warehouse',
                'loc.name as location',
                'u.symbol as unit',
                'b.symbol as base_unit',
                'l.notes',
            ]);

        foreach ([
            'quantity',
            'conversion_factor',
            'base_quantity',
            'unit_cost',
            'base_unit_cost',
            'total_cost',
            'balance_after',
            'average_cost_after',
        ] as $field) {
            $lines->selectRaw(
                "CAST(l.{$field} AS VARCHAR(80)) AS {$field}"
            );
        }

        $lines->orderBy('l.line_number')->orderBy('l.id');

        return [
            'header' => $this->row($header),
            'total_cost' => (string) $total->total,
            'lines' => $this->page($lines, $page),
        ];
    }

    public function kardex(array $filters): ?array
    {
        $productId = (int) $filters['product_id'];

        $product = DB::table('inventory_products as p')
            ->join('inventory_units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.id', $productId)
            ->first(['p.id', 'p.sku', 'p.name', 'u.symbol as unit']);

        if ($product === null) {
            return null;
        }

        $base = DB::table('inventory_movement_lines as l')
            ->join('inventory_movements as m', 'm.id', '=', 'l.movement_id')
            ->where('l.product_id', $productId)
            ->where('m.status', 'posted');

        if (! empty($filters['location_id'])) {
            $base->where('l.location_id', $filters['location_id']);
        }

        $signed = "
            CASE
                WHEN m.direction = 'inbound' THEN l.base_quantity
                ELSE -l.base_quantity
            END
        ";

        $opening = '0';

        if (! empty($filters['from'])) {
            $opening = $this->sum(
                (clone $base)->where('m.posted_at', '<', $filters['from']),
                $signed
            );
        }

        $period = clone $base;

        $this->dates($period, 'm.posted_at', $filters);

        $incoming = $this->sum(
            (clone $period)->where('m.direction', 'inbound'),
            'l.base_quantity'
        );

        $outgoing = $this->sum(
            (clone $period)->where('m.direction', 'outbound'),
            'l.base_quantity'
        );

        $closing = (string) BigDecimal::of($opening)
            ->plus($incoming)
            ->minus($outgoing);

        $ledgerTotal = $this->sum(clone $base, $signed);

        $stock = DB::table('inventory_stock_balances')
            ->where('product_id', $productId);

        if (! empty($filters['location_id'])) {
            $stock->where('location_id', $filters['location_id']);
        }

        $current = $this->sum($stock, 'quantity');

        // El saldo se calcula sobre toda la historia del alcance elegido.
        // Las fechas y la paginación se aplican en la consulta exterior.
        $window = (clone $base)
            ->select([
                'l.id',
                'l.movement_id',
                'l.line_number',
                'l.location_id',
                'l.base_unit_id',
                'l.base_quantity',
                'l.total_cost',
                'l.average_cost_after',
                'm.posted_at',
                'm.document_date',
                'm.direction',
                'm.reason',
                'm.reference',
            ])
            ->selectRaw("
                SUM({$signed}) OVER (
                    ORDER BY
                        m.posted_at,
                        m.id,
                        l.line_number,
                        l.id
                    ROWS UNBOUNDED PRECEDING
                ) AS running_balance
            ");

        $rows = DB::query()
            ->fromSub($window, 'k')
            ->join('inventory_locations as loc', 'loc.id', '=', 'k.location_id')
            ->join(
                'inventory_warehouses as w',
                'w.id',
                '=',
                'loc.warehouse_id'
            )
            ->join('inventory_units as u', 'u.id', '=', 'k.base_unit_id');

        $this->dates($rows, 'k.posted_at', $filters);

        $rows->select([
            'k.id',
            'k.movement_id',
            'k.posted_at',
            'k.document_date',
            'k.direction',
            'k.reason',
            'k.reference',
            'w.name as warehouse',
            'loc.name as location',
            'u.symbol as unit',
        ]);

        foreach ([
            'base_quantity',
            'total_cost',
            'average_cost_after',
            'running_balance',
        ] as $field) {
            $rows->selectRaw(
                "CAST(k.{$field} AS VARCHAR(80)) AS {$field}"
            );
        }

        $rows
            ->orderBy('k.posted_at')
            ->orderBy('k.movement_id')
            ->orderBy('k.line_number')
            ->orderBy('k.id');

        return [
            'product' => $this->row($product),
            'summary' => [
                'opening' => $opening,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'closing' => $closing,
                'current' => $current,
                'ledger_total' => $ledgerTotal,
                'difference' => (string) BigDecimal::of($current)
                    ->minus($ledgerTotal),
            ],
            'rows' => $this->page(
                $rows,
                (int) ($filters['page'] ?? 1)
            ),
        ];
    }

    private function dates(
        Builder $query,
        string $column,
        array $filters,
    ): void {
        if (! empty($filters['from'])) {
            $query->where($column, '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where(
                $column,
                '<',
                CarbonImmutable::parse($filters['to'])
                    ->addDay()
                    ->format('Y-m-d')
            );
        }
    }

    private function sum(Builder $query, string $expression): string
    {
        $result = $query
            ->selectRaw(
                "CAST(COALESCE(SUM({$expression}), 0) AS VARCHAR(80)) AS total"
            )
            ->first();

        return (string) $result->total;
    }

    private function page(Builder $query, int $page): array
    {
        $paginator = $query->paginate(20, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (object $row): array => $this->row($row))
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    private function row(object $row): array
    {
        $data = (array) $row;

        foreach ([
            'id',
            'product_id',
            'movement_id',
            'line_number',
            'line_count',
        ] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }

        return $data;
    }
}
