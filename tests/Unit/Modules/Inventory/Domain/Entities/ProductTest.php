<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Inventory\Domain\Entities;

use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Enums\ProductType;
use App\Modules\Inventory\Domain\Exceptions\InvalidProductException;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function test_it_creates_a_valid_product(): void
    {
        $product = Product::create(
            sku: Sku::fromString('alu-nat-001'),
            name: 'Perfil de aluminio natural',
            categoryId: 1,
            baseUnitId: 1,
            productType: ProductType::MATERIAL,
            minimumStock: '10',
            maximumStock: '100',
            reorderPoint: '20',
            averageCost: '150.50',
            lastPurchaseCost: '155.75',
            salePrice: '225.90',
            trackRemnants: true,
        );

        $this->assertNull($product->id());
        $this->assertSame('ALU-NAT-001', $product->sku()->value());
        $this->assertSame('10.0000', $product->minimumStock());
        $this->assertSame('100.0000', $product->maximumStock());
        $this->assertSame('150.5000', $product->averageCost());
        $this->assertTrue($product->tracksRemnants());
        $this->assertTrue($product->isActive());
    }

    public function test_it_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidProductException::class);

        Product::create(
            sku: Sku::fromString('VID-001'),
            name: '   ',
            categoryId: 1,
            baseUnitId: 1,
        );
    }

    public function test_it_rejects_an_invalid_category(): void
    {
        $this->expectException(InvalidProductException::class);

        Product::create(
            sku: Sku::fromString('VID-001'),
            name: 'Vidrio transparente',
            categoryId: 0,
            baseUnitId: 1,
        );
    }

    public function test_it_rejects_maximum_stock_lower_than_minimum(): void
    {
        $this->expectException(InvalidProductException::class);

        Product::create(
            sku: Sku::fromString('PVC-001'),
            name: 'Perfil PVC blanco',
            categoryId: 1,
            baseUnitId: 1,
            minimumStock: '20',
            maximumStock: '10',
        );
    }

    public function test_it_rejects_negative_commercial_values(): void
    {
        $this->expectException(InvalidProductException::class);

        Product::create(
            sku: Sku::fromString('ACC-001'),
            name: 'Bisagra',
            categoryId: 1,
            baseUnitId: 1,
            salePrice: '-10',
        );
    }

    public function test_it_assigns_an_identity_only_once(): void
    {
        $product = Product::create(
            sku: Sku::fromString('HER-001'),
            name: 'Cerradura',
            categoryId: 1,
            baseUnitId: 1,
        );

        $product->assignId(25);

        $this->assertSame(25, $product->id());

        $this->expectException(InvalidProductException::class);

        $product->assignId(30);
    }

    public function test_it_can_be_deactivated_and_activated(): void
    {
        $product = Product::create(
            sku: Sku::fromString('CON-001'),
            name: 'Silicón transparente',
            categoryId: 1,
            baseUnitId: 1,
            productType: ProductType::CONSUMABLE,
        );

        $product->deactivate();

        $this->assertFalse($product->isActive());

        $product->activate();

        $this->assertTrue($product->isActive());
    }
}
