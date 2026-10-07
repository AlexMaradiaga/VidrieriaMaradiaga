<?php

declare(strict_types=1);

namespace App\Modules\Business\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Ports\InventoryDashboardQueryInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessDashboardController extends Controller
{
    public function __invoke(InventoryDashboardQueryInterface $inventory): Response
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth()->toDateString();
        $monthEnd = $today->endOfMonth()->toDateString();
        $canInventory = Gate::allows('inventory.products.view');
        $canSales = Gate::allows('sales.view');
        $canPurchases = Gate::allows('purchases.view');
        $canAccounting = Gate::allows('accounting.reports.view');

        $salesMonth = $canSales ? $this->sum('sales_documents', 'total', 'document_date', $monthStart, $monthEnd) : '0.00';
        $receivables = $canSales ? (string) DB::table('sales_documents')->where('status', 'posted')->selectRaw('COALESCE(SUM(total-paid_amount),0) as amount')->value('amount') : '0.00';
        $purchasesMonth = $canPurchases ? $this->sum('purchasing_documents', 'total', 'document_date', $monthStart, $monthEnd) : '0.00';
        $purchasePayable = $canPurchases ? (string) DB::table('purchasing_documents')->where('status', 'posted')->selectRaw('COALESCE(SUM(total-paid_amount),0) as amount')->value('amount') : '0.00';
        $expensePayable = $canAccounting ? (string) DB::table('accounting_expenses')->where('status', 'posted')->selectRaw('COALESCE(SUM(total-paid_amount),0) as amount')->value('amount') : '0.00';
        $expensesMonth = $canAccounting ? $this->sum('accounting_expenses', 'total', 'expense_date', $monthStart, $monthEnd) : '0.00';
        $cashBalance = $canAccounting ? (string) DB::table('accounting_treasury_accounts as t')
            ->leftJoin('accounting_journal_lines as l', 'l.account_id', '=', 't.accounting_account_id')
            ->leftJoin('accounting_journal_entries as e', function ($join): void { $join->on('e.id', '=', 'l.entry_id')->where('e.status', 'posted'); })
            ->selectRaw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit-l.credit ELSE 0 END),0) as amount')->value('amount') : '0.00';

        return Inertia::render('Dashboard/Index', [
            'inventory' => $canInventory ? $inventory->get() : null,
            'summary' => [
                'sales_month' => $salesMonth, 'receivables' => $receivables,
                'purchases_month' => $purchasesMonth, 'payables' => (string) BigDecimal::of($purchasePayable)->plus($expensePayable)->toScale(2),
                'expenses_month' => $expensesMonth, 'cash_balance' => $cashBalance,
            ],
            'chart' => $this->chart($today, $canSales, $canPurchases, $canAccounting),
            'dueAlerts' => $this->dueAlerts($today, $canPurchases, $canAccounting),
            'recent' => $this->recent($canSales, $canPurchases, $canAccounting),
        ]);
    }

    private function sum(string $table, string $amount, string $date, string $from, string $to): string
    {
        return (string) DB::table($table)->where('status', 'posted')->whereBetween($date, [$from, $to])->selectRaw("COALESCE(SUM({$amount}),0) as amount")->value('amount');
    }

    /** @return list<array{key:string,label:string,sales:string,purchases:string,expenses:string}> */
    private function chart(CarbonImmutable $today, bool $sales, bool $purchases, bool $accounting): array
    {
        $first = $today->startOfMonth()->subMonths(5);
        $saleRows = $sales ? DB::table('sales_documents')->where('status', 'posted')->where('document_date', '>=', $first->toDateString())->get(['document_date', 'total']) : collect();
        $purchaseRows = $purchases ? DB::table('purchasing_documents')->where('status', 'posted')->where('document_date', '>=', $first->toDateString())->get(['document_date', 'total']) : collect();
        $expenseRows = $accounting ? DB::table('accounting_expenses')->where('status', 'posted')->where('expense_date', '>=', $first->toDateString())->get(['expense_date', 'total']) : collect();

        $result = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $first->addMonths($i); $key = $month->format('Y-m');
            $result[] = ['key' => $key, 'label' => ucfirst($month->locale('es')->translatedFormat('M')),
                'sales' => $this->monthTotal($saleRows, 'document_date', $key),
                'purchases' => $this->monthTotal($purchaseRows, 'document_date', $key),
                'expenses' => $this->monthTotal($expenseRows, 'expense_date', $key)];
        }
        return $result;
    }

    private function monthTotal(Collection $rows, string $field, string $key): string
    {
        return number_format((float) $rows->filter(fn ($row): bool => substr((string) $row->{$field}, 0, 7) === $key)->sum(fn ($row): float => (float) $row->total), 2, '.', '');
    }

    private function dueAlerts(CarbonImmutable $today, bool $purchases, bool $accounting): array
    {
        $alerts = collect(); $limit = $today->addDays(7)->toDateString();
        if ($purchases) {
            $alerts = $alerts->concat(DB::table('purchasing_documents as p')->join('inventory_suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('p.status', 'posted')->whereColumn('p.paid_amount', '<', 'p.total')->whereNotNull('p.due_date')->where('p.due_date', '<=', $limit)
                ->get(['p.id', 'p.number', 'p.due_date', 'p.total', 'p.paid_amount', 's.legal_name as party'])
                ->map(fn ($row): array => ['type' => 'purchase', 'number' => $row->number, 'party' => $row->party, 'due_date' => $row->due_date, 'balance' => number_format((float) $row->total - (float) $row->paid_amount, 2, '.', '')]));
        }
        if ($accounting) {
            $alerts = $alerts->concat(DB::table('accounting_expenses')->where('status', 'posted')->whereColumn('paid_amount', '<', 'total')->whereNotNull('due_date')->where('due_date', '<=', $limit)
                ->get(['number', 'due_date', 'total', 'paid_amount', 'payee'])
                ->map(fn ($row): array => ['type' => 'expense', 'number' => $row->number, 'party' => $row->payee, 'due_date' => $row->due_date, 'balance' => number_format((float) $row->total - (float) $row->paid_amount, 2, '.', '')]));
        }
        return $alerts->sortBy('due_date')->take(8)->values()->all();
    }

    private function recent(bool $sales, bool $purchases, bool $accounting): array
    {
        $rows = collect();
        if ($sales) $rows = $rows->concat(DB::table('sales_documents')->orderByDesc('id')->limit(6)->get(['number', 'document_date as date', 'total'])->map(fn ($r): array => ['type' => 'Venta', 'number' => $r->number, 'date' => $r->date, 'amount' => (string) $r->total]));
        if ($purchases) $rows = $rows->concat(DB::table('purchasing_documents')->orderByDesc('id')->limit(6)->get(['number', 'document_date as date', 'total'])->map(fn ($r): array => ['type' => 'Compra', 'number' => $r->number, 'date' => $r->date, 'amount' => (string) $r->total]));
        if ($accounting) $rows = $rows->concat(DB::table('accounting_expenses')->orderByDesc('id')->limit(6)->get(['number', 'expense_date as date', 'total'])->map(fn ($r): array => ['type' => 'Gasto', 'number' => $r->number, 'date' => $r->date, 'amount' => (string) $r->total]));
        return $rows->sortByDesc(fn ($row): string => $row['date'].'-'.$row['number'])->take(10)->values()->all();
    }
}
