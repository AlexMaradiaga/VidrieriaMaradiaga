<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Ports;

interface AccountingPostingInterface
{
    /** @param list<array{account_id:int,debit:string|int|float,credit:string|int|float,description?:string|null}> $lines */
    public function post(array $header, array $lines, int $userId): int;

    public function postSale(int $saleId, array $totals, int $userId): int;

    public function postPayment(int $paymentId, string $amount, string $date, int $userId, ?int $treasuryAccountingId = null): int;

    public function postPurchase(int $purchaseId, array $totals, int $userId): int;

    public function postSupplierPayment(int $paymentId, array $data, int $userId): int;

    public function postExpense(int $expenseId, array $data, int $userId): int;

    public function postExpensePayment(int $paymentId, array $data, int $userId): int;

    public function postLoan(int $loanId, array $data, int $userId): int;

    public function postLoanPayment(int $paymentId, array $data, int $userId): int;
}
