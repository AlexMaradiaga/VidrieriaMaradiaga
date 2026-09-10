<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

use App\Modules\Inventory\Domain\Exceptions\InvalidProductException;

final readonly class Sku
{
    private const MAXIMUM_LENGTH = 50;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper(trim($value));

        if ($normalizedValue === '') {
            throw InvalidProductException::emptySku();
        }

        if (strlen($normalizedValue) > self::MAXIMUM_LENGTH) {
            throw InvalidProductException::skuTooLong(
                self::MAXIMUM_LENGTH
            );
        }

        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $normalizedValue)) {
            throw InvalidProductException::invalidSkuFormat();
        }

        return new self($normalizedValue);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
