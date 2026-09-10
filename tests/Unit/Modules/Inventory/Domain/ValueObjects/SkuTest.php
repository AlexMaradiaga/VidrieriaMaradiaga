<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Inventory\Domain\ValueObjects;

use App\Modules\Inventory\Domain\Exceptions\InvalidProductException;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use PHPUnit\Framework\TestCase;

final class SkuTest extends TestCase
{
    public function test_it_normalizes_a_valid_sku(): void
    {
        $sku = Sku::fromString('  alu-natural-001  ');

        $this->assertSame('ALU-NATURAL-001', $sku->value());
        $this->assertSame('ALU-NATURAL-001', (string) $sku);
    }

    public function test_it_rejects_an_empty_sku(): void
    {
        $this->expectException(InvalidProductException::class);
        $this->expectExceptionMessage(
            'El código SKU del producto es obligatorio.'
        );

        Sku::fromString('   ');
    }

    public function test_it_rejects_a_sku_with_spaces(): void
    {
        $this->expectException(InvalidProductException::class);

        Sku::fromString('ALU NATURAL 001');
    }

    public function test_it_rejects_a_sku_longer_than_fifty_characters(): void
    {
        $this->expectException(InvalidProductException::class);

        Sku::fromString(str_repeat('A', 51));
    }

    public function test_it_compares_two_skus_by_their_value(): void
    {
        $firstSku = Sku::fromString('vid-001');
        $secondSku = Sku::fromString('VID-001');
        $differentSku = Sku::fromString('VID-002');

        $this->assertTrue($firstSku->equals($secondSku));
        $this->assertFalse($firstSku->equals($differentSku));
    }
}
