<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Enums;

enum ProductType: string
{
    case MATERIAL = 'material';
    case FINISHED_PRODUCT = 'finished_product';
    case CONSUMABLE = 'consumable';

    public function label(): string
    {
        return match ($this) {
            self::MATERIAL => 'Materia prima',
            self::FINISHED_PRODUCT => 'Producto terminado',
            self::CONSUMABLE => 'Consumible',
        };
    }
}
