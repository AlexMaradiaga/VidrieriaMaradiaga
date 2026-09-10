<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Inventory\Application\Handlers;

use App\Modules\Inventory\Application\Commands\CreateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductCreationException;
use App\Modules\Inventory\Application\Handlers\CreateProductHandler;
use App\Modules\Inventory\Application\Ports\ProductCatalogLookupInterface;
use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateProductHandlerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $products;

    private ProductCatalogLookupInterface&MockObject $catalog;

    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = $this->createMock(
            ProductRepositoryInterface::class
        );

        $this->catalog = $this->createMock(
            ProductCatalogLookupInterface::class
        );

        $this->handler = new CreateProductHandler(
            $this->products,
            $this->catalog,
        );
    }

    public function test_it_creates_a_product_with_zero_initial_costs(): void
    {
        $this->products
            ->expects($this->once())
            ->method('existsBySku')
            ->with($this->callback(
                fn (Sku $sku): bool => $sku->value() === 'ALU-001'
            ))
            ->willReturn(false);

        $this->catalog
            ->expects($this->once())
            ->method('activeCategoryExists')
            ->with(10)
            ->willReturn(true);

        $this->catalog
            ->expects($this->once())
            ->method('activeUnitExists')
            ->with(20)
            ->willReturn(true);

        $this->products
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Product $product): Product {
                $this->assertNull($product->id());
                $this->assertSame('ALU-001', $product->sku()->value());
                $this->assertSame('Perfil natural', $product->name());
                $this->assertSame(10, $product->categoryId());
                $this->assertSame(20, $product->baseUnitId());
                $this->assertSame('0.0000', $product->averageCost());
                $this->assertSame('0.0000', $product->lastPurchaseCost());
                $this->assertSame('250.1250', $product->salePrice());
                $this->assertTrue($product->tracksRemnants());

                // Simular el ID que devolvería el repositorio.
                $product->assignId(100);

                return $product;
            });

        $result = $this->handler->handle(
            new CreateProductCommand(
                sku: ' alu-001 ',
                name: 'Perfil natural',
                categoryId: 10,
                baseUnitId: 20,
                salePrice: '250.125',
                trackRemnants: true,
            )
        );

        $this->assertSame(100, $result->id());
        $this->assertTrue($result->isActive());
    }

    public function test_it_rejects_a_duplicate_sku_without_saving(): void
    {
        $this->products
            ->expects($this->once())
            ->method('existsBySku')
            ->willReturn(true);

        $this->products
            ->expects($this->never())
            ->method('save');

        $this->catalog
            ->expects($this->never())
            ->method('activeCategoryExists');

        $this->catalog
            ->expects($this->never())
            ->method('activeUnitExists');

        $this->expectException(ProductCreationException::class);

        $this->expectExceptionMessage(
            'El SKU ya está registrado, incluso si el producto fue eliminado.'
        );

        $this->handler->handle($this->validCommand());
    }

    public function test_it_rejects_an_unavailable_category_without_saving(): void
    {
        $this->products
            ->method('existsBySku')
            ->willReturn(false);

        $this->catalog
            ->expects($this->once())
            ->method('activeCategoryExists')
            ->with(10)
            ->willReturn(false);

        $this->catalog
            ->expects($this->never())
            ->method('activeUnitExists');

        $this->products
            ->expects($this->never())
            ->method('save');

        $this->expectException(ProductCreationException::class);

        $this->expectExceptionMessage(
            'La categoría no existe o está inactiva.'
        );

        $this->handler->handle($this->validCommand());
    }

    public function test_it_rejects_an_unavailable_unit_without_saving(): void
    {
        $this->products
            ->method('existsBySku')
            ->willReturn(false);

        $this->catalog
            ->method('activeCategoryExists')
            ->willReturn(true);

        $this->catalog
            ->expects($this->once())
            ->method('activeUnitExists')
            ->with(20)
            ->willReturn(false);

        $this->products
            ->expects($this->never())
            ->method('save');

        $this->expectException(ProductCreationException::class);

        $this->expectExceptionMessage(
            'La unidad base no existe o está inactiva.'
        );

        $this->handler->handle($this->validCommand());
    }

    public function test_it_rejects_an_invalid_product_type(): void
    {
        $this->products
            ->expects($this->never())
            ->method('save');

        $this->expectException(ProductCreationException::class);

        $this->expectExceptionMessage(
            'El tipo de producto no es válido.'
        );

        $this->handler->handle(
            new CreateProductCommand(
                sku: 'ALU-001',
                name: 'Perfil natural',
                categoryId: 10,
                baseUnitId: 20,
                productType: 'tipo-inexistente',
            )
        );
    }

    public function test_it_rejects_remnant_tracking_without_stock_tracking(): void
    {
        $this->products
            ->expects($this->never())
            ->method('save');

        $this->expectException(ProductCreationException::class);

        $this->expectExceptionMessage(
            'Para controlar lotes, retazos o existencias negativas, '
            .'debes activar el control de stock.'
        );

        $this->handler->handle(
            new CreateProductCommand(
                sku: 'ALU-001',
                name: 'Perfil natural',
                categoryId: 10,
                baseUnitId: 20,
                trackStock: false,
                trackRemnants: true,
            )
        );
    }

    private function validCommand(): CreateProductCommand
    {
        return new CreateProductCommand(
            sku: 'ALU-001',
            name: 'Perfil natural',
            categoryId: 10,
            baseUnitId: 20,
        );
    }
}
