<?php

declare(strict_types=1);

namespace App\Modules\Accounting\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AccountingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('accounting.entries.view');
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();
        $trialBalance = DB::table('accounting_accounts as a')->leftJoin('accounting_journal_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('accounting_journal_entries as e', function ($join) use ($from, $to): void {
                $join->on('e.id', '=', 'l.entry_id')->where('e.status', 'posted')->whereBetween('e.entry_date', [$from, $to]);
            })->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.code')
            ->get(['a.id', 'a.code', 'a.name', 'a.type', DB::raw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit ELSE 0 END),0) as debit'), DB::raw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.credit ELSE 0 END),0) as credit')]);
        $totalDebit = $trialBalance->sum(fn ($row): float => (float) $row->debit);
        $totalCredit = $trialBalance->sum(fn ($row): float => (float) $row->credit);
        $revenue = $trialBalance->where('type', 'revenue')->sum(fn ($row): float => (float) $row->credit - (float) $row->debit);
        $expenses = $trialBalance->where('type', 'expense')->sum(fn ($row): float => (float) $row->debit - (float) $row->credit);
        $assets = $trialBalance->where('type', 'asset')->sum(fn ($row): float => (float) $row->debit - (float) $row->credit);
        $liabilities = $trialBalance->where('type', 'liability')->sum(fn ($row): float => (float) $row->credit - (float) $row->debit);
        $equity = $trialBalance->where('type', 'equity')->sum(fn ($row): float => (float) $row->credit - (float) $row->debit);
        return Inertia::render('Accounting/Index', [
            'filters' => compact('from', 'to'),
            'accounts' => DB::table('accounting_accounts')->orderBy('code')->get(),
            'periods' => DB::table('accounting_periods')->orderByDesc('year')->orderByDesc('month')->get(),
            'entries' => DB::table('accounting_journal_entries')->whereBetween('entry_date', [$from, $to])->orderByDesc('entry_date')->orderByDesc('id')->get(),
            'trialBalance' => $trialBalance,
            'statements' => [
                'debits' => number_format($totalDebit, 2, '.', ''), 'credits' => number_format($totalCredit, 2, '.', ''),
                'difference' => number_format($totalDebit - $totalCredit, 2, '.', ''),
                'revenue' => number_format($revenue, 2, '.', ''), 'expenses' => number_format($expenses, 2, '.', ''),
                'profit' => number_format($revenue - $expenses, 2, '.', ''), 'assets' => number_format($assets, 2, '.', ''),
                'liabilities' => number_format($liabilities, 2, '.', ''), 'equity' => number_format($equity, 2, '.', ''),
                'equation_difference' => number_format($assets - ($liabilities + $equity + $revenue - $expenses), 2, '.', ''),
            ],
        ]);
    }

    public function account(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.accounts.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounting_accounts,code'], 'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'], 'accepts_entries' => ['required', 'boolean'],
        ]);
        DB::table('accounting_accounts')->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Cuenta contable creada.');
    }

    public function period(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.periods.manage');
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200'], 'month' => ['required', 'integer', 'between:1,12'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $exists = DB::table('accounting_periods')->where('year', $data['year'])->where('month', $data['month'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['month' => 'El período ya existe.']);
        }
        DB::table('accounting_periods')->insert([...$data, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Período contable abierto.');
    }

    public function closePeriod(Request $request, int $periodId): RedirectResponse
    {
        Gate::authorize('accounting.periods.manage');
        DB::table('accounting_periods')->where('id', $periodId)->where('status', 'open')->update([
            'status' => 'closed', 'closed_by' => $request->user()->getAuthIdentifier(), 'closed_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Período cerrado.');
    }

    public function journal(Request $request, AccountingPostingInterface $posting): RedirectResponse
    {
        Gate::authorize('accounting.entries.create');
        $data = $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'], 'description' => ['required', 'string', 'max:250'],
            'reference' => ['nullable', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:250'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'], 'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);
        try {
            $posting->post([...$data, 'source_type' => 'manual'], $data['lines'], (int) $request->user()->getAuthIdentifier());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['entry' => $exception->getMessage()]);
        }
        return back()->with('success', 'Asiento contable publicado.');
    }
}
