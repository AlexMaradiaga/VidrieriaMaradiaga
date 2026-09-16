<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface InventoryOperationsQueryInterface
{
    public function options(): array;

    public function alerts(): array;

    public function counts(): array;

    public function remnants(): array;

    public function kits(): array;

    public function catalogs(): array;
}
