<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface InventoryEntryOptionsInterface
{
    public function get(): array;
}
