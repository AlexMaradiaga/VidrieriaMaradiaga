<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface InventoryMovementQueryInterface
{
    public function options(): array;

    public function entries(array $filters): array;

    public function entry(int $movementId, int $page = 1): ?array;

    public function kardex(array $filters): ?array;
}
