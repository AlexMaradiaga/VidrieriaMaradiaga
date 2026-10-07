<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Persistence\Services;

use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SqlAccountingPosting implements AccountingPostingInterface
{
    public function post(array $header, array $lines, int $userId): int
    {
        return DB::transaction(function () use ($header, $lines, $userId): int {
            $date = (string) $header['entry_date'];
            $period = DB::table('accounting_periods')
                ->where('starts_on', '<=', $date)
                ->where('ends_on', '>=', $date)
                ->lockForUpdate()
                ->first();

            if ($period === null || $period->status !== 'open') {
                throw new DomainException('La fecha no pertenece a un período contable abierto.');
            }

            $debits = BigDecimal::zero()->toScale(2);
            $credits = BigDecimal::zero()->toScale(2);
            $normalized = [];

            foreach ($lines as $line) {
                $debit = BigDecimal::of((string) $line['debit'])->toScale(2, RoundingMode::HALF_UP);
                $credit = BigDecimal::of((string) $line['credit'])->toScale(2, RoundingMode::HALF_UP);
                if ($debit->isLessThan('0') || $credit->isLessThan('0') || ($debit->isGreaterThan('0') && $credit->isGreaterThan('0'))) {
                    throw new DomainException('Cada línea debe tener débito o crédito, nunca ambos.');
                }
                if ($debit->isZero() && $credit->isZero()) {
                    continue;
                }

                $account = DB::table('accounting_accounts')->where('id', (int) $line['account_id'])->first();
                if ($account === null || ! $account->active || ! $account->accepts_entries) {
                    throw new DomainException('Una cuenta contable no está disponible para movimientos.');
                }

                $debits = $debits->plus($debit);
                $credits = $credits->plus($credit);
                $normalized[] = [...$line, 'debit' => (string) $debit, 'credit' => (string) $credit];
            }

            if ($debits->isEqualTo('0') || ! $debits->isEqualTo($credits)) {
                throw new DomainException('El asiento no está cuadrado: débitos y créditos deben ser iguales.');
            }

            $sequence = (int) DB::table('accounting_journal_entries')->lockForUpdate()->max('id') + 1;
            $number = sprintf('ASI-%s-%06d', str_replace('-', '', $date), $sequence);
            $now = now();
            $entryId = DB::table('accounting_journal_entries')->insertGetId([
                'number' => $number,
                'entry_date' => $date,
                'period_id' => $period->id,
                'source_type' => $header['source_type'] ?? 'manual',
                'source_id' => $header['source_id'] ?? null,
                'description' => trim((string) $header['description']),
                'reference' => trim((string) ($header['reference'] ?? '')) ?: null,
                'status' => 'posted',
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($normalized as $index => $line) {
                DB::table('accounting_journal_lines')->insert([
                    'entry_id' => $entryId,
                    'line_number' => $index + 1,
                    'account_id' => $line['account_id'],
                    'description' => trim((string) ($line['description'] ?? '')) ?: null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return (int) $entryId;
        }, 5);
    }

    public function postSale(int $saleId, array $totals, int $userId): int
    {
        $accounts = $this->settings(['cash', 'accounts_receivable', 'inventory', 'sales_revenue', 'sales_tax_payable', 'cost_of_goods_sold']);
        $paid = BigDecimal::of((string) $totals['paid'])->toScale(2);
        $total = BigDecimal::of((string) $totals['total'])->toScale(2);
        $cost = BigDecimal::of((string) $totals['cost'])->toScale(2, RoundingMode::HALF_UP);
        $receivable = $total->minus($paid);

        return $this->post([
            'entry_date' => $totals['date'],
            'source_type' => 'sale',
            'source_id' => $saleId,
            'description' => 'Venta '.$totals['number'],
            'reference' => $totals['number'],
        ], [
            ['account_id' => (int) ($totals['treasury_accounting_id'] ?? $accounts['cash']), 'debit' => (string) $paid, 'credit' => '0'],
            ['account_id' => $accounts['accounts_receivable'], 'debit' => (string) $receivable, 'credit' => '0'],
            ['account_id' => $accounts['cost_of_goods_sold'], 'debit' => (string) $cost, 'credit' => '0'],
            ['account_id' => $accounts['sales_revenue'], 'debit' => '0', 'credit' => (string) $totals['net']],
            ['account_id' => $accounts['sales_tax_payable'], 'debit' => '0', 'credit' => (string) $totals['tax']],
            ['account_id' => $accounts['inventory'], 'debit' => '0', 'credit' => (string) $cost],
        ], $userId);
    }

    public function postPayment(int $paymentId, string $amount, string $date, int $userId, ?int $treasuryAccountingId = null): int
    {
        $accounts = $this->settings(['cash', 'accounts_receivable']);

        return $this->post([
            'entry_date' => $date,
            'source_type' => 'sale_payment',
            'source_id' => $paymentId,
            'description' => 'Cobro de venta',
            'reference' => 'COBRO-'.$paymentId,
        ], [
            ['account_id' => $treasuryAccountingId ?? $accounts['cash'], 'debit' => $amount, 'credit' => '0'],
            ['account_id' => $accounts['accounts_receivable'], 'debit' => '0', 'credit' => $amount],
        ], $userId);
    }

    public function postPurchase(int $purchaseId, array $totals, int $userId): int
    {
        $accounts = $this->settings(['inventory', 'input_tax', 'accounts_payable']);
        $total = BigDecimal::of((string) $totals['total'])->toScale(2);
        $paid = BigDecimal::of((string) $totals['paid'])->toScale(2);
        $payable = $total->minus($paid);

        return $this->post([
            'entry_date' => $totals['date'],
            'source_type' => 'purchase',
            'source_id' => $purchaseId,
            'description' => 'Compra '.$totals['number'],
            'reference' => $totals['reference'] ?? $totals['number'],
        ], [
            ['account_id' => $accounts['inventory'], 'debit' => $totals['net'], 'credit' => '0'],
            ['account_id' => $accounts['input_tax'], 'debit' => $totals['tax'], 'credit' => '0'],
            ['account_id' => (int) $totals['treasury_accounting_id'], 'debit' => '0', 'credit' => (string) $paid],
            ['account_id' => $accounts['accounts_payable'], 'debit' => '0', 'credit' => (string) $payable],
        ], $userId);
    }

    public function postSupplierPayment(int $paymentId, array $data, int $userId): int
    {
        $accounts = $this->settings(['accounts_payable']);

        return $this->post([
            'entry_date' => $data['date'], 'source_type' => 'supplier_payment', 'source_id' => $paymentId,
            'description' => 'Pago a proveedor', 'reference' => $data['reference'] ?? 'PAGO-PROV-'.$paymentId,
        ], [
            ['account_id' => $accounts['accounts_payable'], 'debit' => $data['amount'], 'credit' => '0'],
            ['account_id' => (int) $data['treasury_accounting_id'], 'debit' => '0', 'credit' => $data['amount']],
        ], $userId);
    }

    public function postExpense(int $expenseId, array $data, int $userId): int
    {
        $accounts = $this->settings(['input_tax', 'accounts_payable']);
        $total = BigDecimal::of((string) $data['total'])->toScale(2);
        $paid = BigDecimal::of((string) $data['paid'])->toScale(2);

        return $this->post([
            'entry_date' => $data['date'], 'source_type' => 'expense', 'source_id' => $expenseId,
            'description' => $data['description'], 'reference' => $data['reference'] ?? null,
        ], [
            ['account_id' => (int) $data['expense_account_id'], 'debit' => $data['subtotal'], 'credit' => '0'],
            ['account_id' => $accounts['input_tax'], 'debit' => $data['tax'], 'credit' => '0'],
            ['account_id' => (int) $data['treasury_accounting_id'], 'debit' => '0', 'credit' => (string) $paid],
            ['account_id' => $accounts['accounts_payable'], 'debit' => '0', 'credit' => (string) $total->minus($paid)],
        ], $userId);
    }

    public function postExpensePayment(int $paymentId, array $data, int $userId): int
    {
        $accounts = $this->settings(['accounts_payable']);

        return $this->post([
            'entry_date' => $data['date'], 'source_type' => 'expense_payment', 'source_id' => $paymentId,
            'description' => 'Pago de gasto o servicio', 'reference' => $data['reference'] ?? 'PAGO-GTO-'.$paymentId,
        ], [
            ['account_id' => $accounts['accounts_payable'], 'debit' => $data['amount'], 'credit' => '0'],
            ['account_id' => (int) $data['treasury_accounting_id'], 'debit' => '0', 'credit' => $data['amount']],
        ], $userId);
    }

    public function postLoan(int $loanId, array $data, int $userId): int
    {
        return $this->post([
            'entry_date' => $data['date'], 'source_type' => 'loan', 'source_id' => $loanId,
            'description' => $data['description'], 'reference' => $data['reference'] ?? null,
        ], [
            ['account_id' => (int) $data['treasury_accounting_id'], 'debit' => $data['principal'], 'credit' => '0'],
            ['account_id' => (int) $data['liability_account_id'], 'debit' => '0', 'credit' => $data['principal']],
        ], $userId);
    }

    public function postLoanPayment(int $paymentId, array $data, int $userId): int
    {
        $accounts = $this->settings(['financial_fees']);

        return $this->post([
            'entry_date' => $data['date'], 'source_type' => 'loan_payment', 'source_id' => $paymentId,
            'description' => 'Pago de préstamo '.$data['loan_number'], 'reference' => $data['reference'] ?? null,
        ], [
            ['account_id' => (int) $data['liability_account_id'], 'debit' => $data['principal'], 'credit' => '0'],
            ['account_id' => (int) $data['interest_account_id'], 'debit' => $data['interest'], 'credit' => '0'],
            ['account_id' => $accounts['financial_fees'], 'debit' => $data['late_fee'], 'credit' => '0'],
            ['account_id' => (int) $data['treasury_accounting_id'], 'debit' => '0', 'credit' => $data['total']],
        ], $userId);
    }

    /** @param list<string> $keys @return array<string,int> */
    private function settings(array $keys): array
    {
        $settings = DB::table('accounting_settings')->whereIn('key', $keys)->pluck('account_id', 'key')->map(fn ($id): int => (int) $id)->all();
        foreach ($keys as $key) {
            if (! isset($settings[$key])) {
                throw new DomainException("Falta configurar la cuenta contable: {$key}.");
            }
        }
        return $settings;
    }
}
