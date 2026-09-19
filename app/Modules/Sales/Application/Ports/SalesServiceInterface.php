<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Ports;

interface SalesServiceInterface
{
    /** @return array{sale_id:int,number:string,repeated:bool} */
    public function create(array $data, int $userId): array;

    public function registerPayment(int $saleId, array $data, int $userId): int;
}
