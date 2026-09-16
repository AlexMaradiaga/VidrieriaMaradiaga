<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface InventoryOperationsInterface
{
    /** @return array{movement_id:int,repeated:bool} */
    public function registerExit(array $data, int $userId): array;

    /** @return array{transfer_id:int,repeated:bool} */
    public function transfer(array $data, int $userId): array;

    /** @return array{count_id:int,repeated:bool} */
    public function registerCount(array $data, int $userId): array;
}
