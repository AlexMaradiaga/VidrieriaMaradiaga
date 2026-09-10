<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class InvalidProductException extends DomainException
{
    public static function emptySku(): self
    {
        return new self('El código SKU del producto es obligatorio.');
    }

    public static function skuTooLong(int $maximumLength): self
    {
        return new self(
            "El código SKU no puede superar los {$maximumLength} caracteres."
        );
    }

    public static function invalidSkuFormat(): self
    {
        return new self(
            'El código SKU solamente puede contener letras, números, puntos, guiones y guiones bajos.'
        );
    }

    public static function invalidIdentifier(string $field): self
    {
        return new self("El identificador {$field} no es válido.");
    }

    public static function emptyName(): self
    {
        return new self('El nombre del producto es obligatorio.');
    }

    public static function nameTooLong(): self
    {
        return new self('El nombre del producto no puede superar los 180 caracteres.');
    }

    public static function barcodeTooLong(): self
    {
        return new self('El código de barras no puede superar los 80 caracteres.');
    }

    public static function descriptionTooLong(): self
    {
        return new self('La descripción no puede superar los 1000 caracteres.');
    }

    public static function invalidDecimal(string $field): self
    {
        return new self(
            "El campo {$field} debe ser un número positivo con un máximo de cuatro decimales."
        );
    }

    public static function maximumStockLowerThanMinimum(): self
    {
        return new self(
            'El stock máximo no puede ser menor que el stock mínimo.'
        );
    }

    public static function identityAlreadyAssigned(): self
    {
        return new self('El producto ya tiene un identificador asignado.');
    }
}
