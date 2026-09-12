<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface RegisterInventoryEntryInterface
{
    /**
     * Recibe datos validados de una entrada de un producto.
     *
     * @return array{movement_id: int, repeated: bool}
     */
    public function register(array $data, int $userId): array;
}
