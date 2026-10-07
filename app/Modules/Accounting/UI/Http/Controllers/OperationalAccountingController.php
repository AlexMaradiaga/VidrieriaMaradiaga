<?php

declare(strict_types=1);

namespace App\Modules\Accounting\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class OperationalAccountingController extends Controller
{
    public function treasury(): Response
    {
        Gate::authorize('accounting.treasury.view');
        return Inertia::render('Accounting/Treasury', [
            'accounts' => DB::table('accounting_treasury_accounts as t')->join('accounting_accounts as a', 'a.id', '=', 't.accounting_account_id')
                ->leftJoin('accounting_journal_lines as l', 'l.account_id', '=', 'a.id')
                ->leftJoin('accounting_journal_entries as e', function ($join): void { $join->on('e.id', '=', 'l.entry_id')->where('e.status', 'posted'); })
                ->groupBy('t.id', 't.code', 't.name', 't.type', 't.bank_name', 't.account_number', 't.active', 'a.code', 'a.name')
                ->orderBy('t.name')->get(['t.id', 't.code', 't.name', 't.type', 't.bank_name', 't.account_number', 't.active',
                    'a.code as ledger_code', 'a.name as ledger_name', DB::raw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit-l.credit ELSE 0 END),0) as balance')]),
            'ledgerAccounts' => DB::table('accounting_accounts')->where('type', 'asset')->where('active', true)->where('accepts_entries', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeTreasury(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.treasury.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounting_treasury_accounts,code'],
            'name' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(['cash', 'bank', 'wallet'])],
            'accounting_account_id' => ['required', 'integer', 'exists:accounting_accounts,id', 'unique:accounting_treasury_accounts,accounting_account_id'],
            'bank_name' => ['nullable', 'string', 'max:120'], 'account_number' => ['nullable', 'string', 'max:80'],
        ]);
        $validAccount = DB::table('accounting_accounts')->where('id', $data['accounting_account_id'])->where('type', 'asset')->where('active', true)->where('accepts_entries', true)->exists();
        if (! $validAccount) throw ValidationException::withMessages(['accounting_account_id' => 'Selecciona una cuenta contable de activo disponible.']);
        DB::table('accounting_treasury_accounts')->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Caja o cuenta bancaria creada.');
    }

    public function expenses(): Response
    {
        Gate::authorize('accounting.expenses.view');
        return Inertia::render('Accounting/Expenses', [
            'expenses' => DB::table('accounting_expenses as e')->join('accounting_accounts as a', 'a.id', '=', 'e.expense_account_id')
                ->orderByDesc('e.expense_date')->orderByDesc('e.id')->get(['e.id', 'e.number', 'e.expense_date', 'e.due_date', 'e.payee', 'e.category',
                    'a.name as account', 'e.document_number', 'e.payment_type', 'e.total', 'e.paid_amount', 'e.status']),
            'expenseAccounts' => DB::table('accounting_accounts')->where('type', 'expense')->where('active', true)->where('accepts_entries', true)->orderBy('code')->get(['id', 'code', 'name']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function storeExpense(Request $request, AccountingPostingInterface $posting): RedirectResponse
    {
        Gate::authorize('accounting.expenses.manage');
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'], 'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expense_date'], 'payee' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['electricity', 'water', 'internet', 'telephone', 'rent', 'fuel', 'maintenance', 'professional', 'tax', 'other'])],
            'expense_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'], 'document_number' => ['nullable', 'string', 'max:100'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])], 'treasury_account_id' => ['nullable', 'integer', 'exists:accounting_treasury_accounts,id'],
            'subtotal' => ['required', 'numeric', 'gt:0'], 'tax' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($data, $request, $posting): void {
                if (DB::table('accounting_expenses')->where('operation_key', strtolower($data['operation_key']))->exists()) return;
                $subtotal = BigDecimal::of((string) $data['subtotal'])->toScale(2, RoundingMode::HALF_UP);
                $tax = BigDecimal::of((string) $data['tax'])->toScale(2, RoundingMode::HALF_UP);
                $total = $subtotal->plus($tax);
                $paid = BigDecimal::of((string) $data['paid_amount'])->toScale(2, RoundingMode::HALF_UP);
                if ($paid->isGreaterThan($total) || ($data['payment_type'] === 'cash' && ! $paid->isEqualTo($total))) {
                    throw new DomainException('El pago debe corresponder con el tipo y total del gasto.');
                }
                $treasury = $paid->isGreaterThan('0')
                    ? DB::table('accounting_treasury_accounts')->where('id', (int) $data['treasury_account_id'])->where('active', true)->first()
                    : null;
                if ($paid->isGreaterThan('0') && $treasury === null) throw new DomainException('Selecciona la cuenta desde la que se pagó.');
                $sequence = (int) DB::table('accounting_expenses')->lockForUpdate()->max('id') + 1;
                $number = sprintf('GTO-%s-%06d', str_replace('-', '', $data['expense_date']), $sequence);
                $now = now(); $userId = (int) $request->user()->getAuthIdentifier();
                $id = DB::table('accounting_expenses')->insertGetId([
                    ...$data, 'operation_key' => strtolower($data['operation_key']), 'number' => $number,
                    'treasury_account_id' => $treasury?->id, 'subtotal' => (string) $subtotal, 'tax' => (string) $tax,
                    'total' => (string) $total, 'paid_amount' => (string) $paid, 'status' => 'posted',
                    'journal_entry_id' => null, 'created_by' => $userId, 'posted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $entryId = $posting->postExpense($id, [
                    'date' => $data['expense_date'], 'description' => 'Gasto '.$number.' - '.$data['payee'], 'reference' => $data['document_number'] ?: $number,
                    'expense_account_id' => $data['expense_account_id'], 'subtotal' => (string) $subtotal, 'tax' => (string) $tax,
                    'total' => (string) $total, 'paid' => (string) $paid, 'treasury_accounting_id' => $treasury?->accounting_account_id,
                ], $userId);
                DB::table('accounting_expenses')->where('id', $id)->update(['journal_entry_id' => $entryId]);
            }, 5);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['expense' => $exception->getMessage()]);
        }
        return back()->with('success', 'Gasto registrado y contabilizado.');
    }

    public function payExpense(Request $request, int $expenseId, AccountingPostingInterface $posting): RedirectResponse
    {
        Gate::authorize('accounting.expenses.manage');
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'amount' => ['required', 'numeric', 'gt:0'],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_treasury_accounts,id'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'check', 'other'])], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            DB::transaction(function () use ($expenseId, $data, $request, $posting): void {
                $expense = DB::table('accounting_expenses')->where('id', $expenseId)->lockForUpdate()->first();
                $treasury = DB::table('accounting_treasury_accounts')->where('id', $data['treasury_account_id'])->where('active', true)->first();
                if ($expense === null || $treasury === null) throw new DomainException('El gasto o la cuenta de pago no está disponible.');
                $amount = BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::HALF_UP);
                $balance = BigDecimal::of((string) $expense->total)->minus((string) $expense->paid_amount);
                if ($amount->isGreaterThan($balance)) throw new DomainException('El pago supera el saldo del gasto.');
                $now = now(); $userId = (int) $request->user()->getAuthIdentifier();
                $id = DB::table('accounting_expense_payments')->insertGetId([
                    'expense_id' => $expenseId, 'payment_date' => $data['payment_date'], 'amount' => (string) $amount,
                    'treasury_account_id' => $treasury->id, 'method' => $data['method'], 'reference' => $data['reference'] ?: null,
                    'paid_by' => $userId, 'journal_entry_id' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $entryId = $posting->postExpensePayment($id, ['date' => $data['payment_date'], 'amount' => (string) $amount,
                    'treasury_accounting_id' => $treasury->accounting_account_id, 'reference' => $data['reference'] ?? null], $userId);
                DB::table('accounting_expense_payments')->where('id', $id)->update(['journal_entry_id' => $entryId]);
                DB::table('accounting_expenses')->where('id', $expenseId)->update(['paid_amount' => (string) BigDecimal::of((string) $expense->paid_amount)->plus($amount), 'updated_at' => $now]);
            }, 5);
        } catch (DomainException $exception) { throw ValidationException::withMessages(['payment' => $exception->getMessage()]); }
        return back()->with('success', 'Pago de gasto registrado.');
    }

    public function loans(): Response
    {
        Gate::authorize('accounting.loans.view');
        return Inertia::render('Accounting/Loans', [
            'loans' => DB::table('accounting_loans')->orderByDesc('start_date')->orderByDesc('id')->get(),
            'liabilityAccounts' => DB::table('accounting_accounts')->where('type', 'liability')->where('active', true)->where('accepts_entries', true)->orderBy('code')->get(['id', 'code', 'name']),
            'expenseAccounts' => DB::table('accounting_accounts')->where('type', 'expense')->where('active', true)->where('accepts_entries', true)->orderBy('code')->get(['id', 'code', 'name']),
            'treasuryAccounts' => DB::table('accounting_treasury_accounts')->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeLoan(Request $request, AccountingPostingInterface $posting): RedirectResponse
    {
        Gate::authorize('accounting.loans.manage');
        $data = $request->validate([
            'creditor' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:250'], 'reference' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'principal' => ['required', 'numeric', 'gt:0'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'installments' => ['required', 'integer', 'min:1', 'max:600'],
            'liability_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'], 'interest_expense_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_treasury_accounts,id'],
        ]);
        try {
            DB::transaction(function () use ($data, $request, $posting): void {
                $treasury = DB::table('accounting_treasury_accounts')->where('id', $data['treasury_account_id'])->where('active', true)->first();
                if ($treasury === null) throw new DomainException('La cuenta que recibió el préstamo no está disponible.');
                $principal = BigDecimal::of((string) $data['principal'])->toScale(2, RoundingMode::HALF_UP);
                $sequence = (int) DB::table('accounting_loans')->lockForUpdate()->max('id') + 1;
                $number = sprintf('PRE-%s-%05d', str_replace('-', '', $data['start_date']), $sequence);
                $now = now(); $userId = (int) $request->user()->getAuthIdentifier();
                $id = DB::table('accounting_loans')->insertGetId([
                    ...$data, 'number' => $number, 'principal' => (string) $principal, 'outstanding_principal' => (string) $principal,
                    'status' => 'active', 'journal_entry_id' => null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $entryId = $posting->postLoan($id, ['date' => $data['start_date'], 'description' => 'Préstamo '.$number.' - '.$data['description'],
                    'reference' => $data['reference'] ?? null, 'principal' => (string) $principal,
                    'liability_account_id' => $data['liability_account_id'], 'treasury_accounting_id' => $treasury->accounting_account_id], $userId);
                DB::table('accounting_loans')->where('id', $id)->update(['journal_entry_id' => $entryId]);
            }, 5);
        } catch (DomainException $exception) { throw ValidationException::withMessages(['loan' => $exception->getMessage()]); }
        return back()->with('success', 'Préstamo registrado y contabilizado.');
    }

    public function payLoan(Request $request, int $loanId, AccountingPostingInterface $posting): RedirectResponse
    {
        Gate::authorize('accounting.loans.manage');
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'principal_amount' => ['required', 'numeric', 'gt:0'],
            'interest_amount' => ['required', 'numeric', 'min:0'], 'late_fee' => ['required', 'numeric', 'min:0'],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_treasury_accounts,id'], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            DB::transaction(function () use ($loanId, $data, $request, $posting): void {
                $loan = DB::table('accounting_loans')->where('id', $loanId)->lockForUpdate()->first();
                $treasury = DB::table('accounting_treasury_accounts')->where('id', $data['treasury_account_id'])->where('active', true)->first();
                if ($loan === null || $loan->status !== 'active' || $treasury === null) throw new DomainException('El préstamo o la cuenta de pago no está disponible.');
                $principal = BigDecimal::of((string) $data['principal_amount'])->toScale(2, RoundingMode::HALF_UP);
                $interest = BigDecimal::of((string) $data['interest_amount'])->toScale(2, RoundingMode::HALF_UP);
                $fee = BigDecimal::of((string) $data['late_fee'])->toScale(2, RoundingMode::HALF_UP);
                $outstanding = BigDecimal::of((string) $loan->outstanding_principal);
                if ($principal->isGreaterThan($outstanding)) throw new DomainException('El abono a capital supera el saldo del préstamo.');
                $total = $principal->plus($interest)->plus($fee); $now = now(); $userId = (int) $request->user()->getAuthIdentifier();
                $id = DB::table('accounting_loan_payments')->insertGetId([
                    'loan_id' => $loanId, 'payment_date' => $data['payment_date'], 'principal_amount' => (string) $principal,
                    'interest_amount' => (string) $interest, 'late_fee' => (string) $fee, 'total' => (string) $total,
                    'treasury_account_id' => $treasury->id, 'reference' => $data['reference'] ?: null,
                    'paid_by' => $userId, 'journal_entry_id' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $entryId = $posting->postLoanPayment($id, ['date' => $data['payment_date'], 'loan_number' => $loan->number,
                    'principal' => (string) $principal, 'interest' => (string) $interest, 'late_fee' => (string) $fee, 'total' => (string) $total,
                    'liability_account_id' => $loan->liability_account_id, 'interest_account_id' => $loan->interest_expense_account_id,
                    'treasury_accounting_id' => $treasury->accounting_account_id, 'reference' => $data['reference'] ?? null], $userId);
                $newBalance = $outstanding->minus($principal);
                DB::table('accounting_loan_payments')->where('id', $id)->update(['journal_entry_id' => $entryId]);
                DB::table('accounting_loans')->where('id', $loanId)->update(['outstanding_principal' => (string) $newBalance,
                    'status' => $newBalance->isZero() ? 'paid' : 'active', 'updated_at' => $now]);
            }, 5);
        } catch (DomainException $exception) { throw ValidationException::withMessages(['payment' => $exception->getMessage()]); }
        return back()->with('success', 'Cuota registrada y separada entre capital, intereses y mora.');
    }
}
