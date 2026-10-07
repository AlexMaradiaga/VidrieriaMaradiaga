<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\Ports;

interface PurchaseServiceInterface
{
    /** @return array{purchase_id:int,number:string,repeated:bool} */
    public function create(array $data, int $userId): array;

    public function registerPayment(int $purchaseId, array $data, int $userId): int;
}
