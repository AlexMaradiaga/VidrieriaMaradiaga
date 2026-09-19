<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Ports;

interface AccountingPostingInterface
{
    /** @param list<array{account_id:int,debit:string|int|float,credit:string|int|float,description?:string|null}> $lines */
    public function post(array $header, array $lines, int $userId): int;

    public function postSale(int $saleId, array $totals, int $userId): int;

    public function postPayment(int $paymentId, string $amount, string $date, int $userId): int;
}
