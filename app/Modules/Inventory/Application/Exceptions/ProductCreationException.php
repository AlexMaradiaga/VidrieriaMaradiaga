<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Exceptions;

use DomainException;

final class ProductCreationException extends DomainException
{
    public static function duplicateSku(): self
    {
        return new self(
            'El SKU ya está registrado, incluso si el producto fue eliminado.'
        );
    }

    public static function unavailableCategory(): self
    {
        return new self(
            'La categoría no existe o está inactiva.'
        );
    }

    public static function unavailableBaseUnit(): self
    {
        return new self(
            'La unidad base no existe o está inactiva.'
        );
    }

    public static function invalidProductType(): self
    {
        return new self(
            'El tipo de producto no es válido.'
        );
    }

    public static function inconsistentStockTracking(): self
    {
        return new self(
            'Para controlar lotes, retazos o existencias negativas, '
            .'debes activar el control de stock.'
        );
    }
}
