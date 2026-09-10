<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Application\Commands\CreateProductCommand;
use App\Modules\Inventory\Application\Exceptions\ProductCreationException;
use App\Modules\Inventory\Application\Handlers\CreateProductHandler;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use Database\Seeders\InventoryCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CreateProductHandlerIntegrationTest extends TestCase
{
    public function test_it_creates_a_product_and_rejects_invalid_catalog_data(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $connection = DB::connection();

        $this->assertSame('sqlsrv', $connection->getDriverName());

        $database = $connection
            ->selectOne('SELECT DB_NAME() AS database_name');

        $this->assertSame(
            'DB_VidrieriaMaradiaga_Test',
            $database->database_name,
            'Esta prueba solamente puede escribir en la base de pruebas.'
        );

        $connection->beginTransaction();

        try {
            $this->seed(InventoryCatalogSeeder::class);

            $categoryId = DB::table('inventory_categories')
                ->where('active', true)
                ->orderBy('id')
                ->value('id');

            $unitId = DB::table('inventory_units')
                ->where('active', true)
                ->orderBy('id')
                ->value('id');

            $this->assertNotNull($categoryId);
            $this->assertNotNull($unitId);

            $categoryId = (int) $categoryId;
            $unitId = (int) $unitId;

            $handler = app(CreateProductHandler::class);
            $repository = app(ProductRepositoryInterface::class);

            $prefix = 'TEST-'.strtoupper(bin2hex(random_bytes(8)));

            // Crear usando las implementaciones reales registradas en Laravel.
            $command = new CreateProductCommand(
                sku: $prefix.'-OK',
                name: 'Perfil creado desde el caso de uso',
                categoryId: $categoryId,
                baseUnitId: $unitId,
                minimumStock: '5.2500',
                salePrice: '325.1234',
                trackRemnants: true,
            );

            $created = $handler->handle($command);

            $this->assertNotNull($created->id());

            $loaded = $repository->findById($created->id());

            $this->assertNotNull($loaded);
            $this->assertSame($command->sku, $loaded->sku()->value());
            $this->assertSame($categoryId, $loaded->categoryId());
            $this->assertSame($unitId, $loaded->baseUnitId());
            $this->assertSame('5.2500', $loaded->minimumStock());
            $this->assertSame('325.1234', $loaded->salePrice());
            $this->assertSame('0.0000', $loaded->averageCost());
            $this->assertSame('0.0000', $loaded->lastPurchaseCost());

            // Intentar crear nuevamente el mismo SKU.
            $this->assertCreationRejected(
                $handler,
                $command,
                'El SKU ya está registrado, incluso si el producto fue eliminado.'
            );

            $this->assertSame(
                1,
                DB::table('inventory_products')
                    ->where('sku', $command->sku)
                    ->count()
            );

            // Desactivar la categoría y comprobar el rechazo.
            DB::table('inventory_categories')
                ->where('id', $categoryId)
                ->update(['active' => false]);

            $inactiveCategoryCommand = new CreateProductCommand(
                sku: $prefix.'-CAT',
                name: 'Producto con categoría inactiva',
                categoryId: $categoryId,
                baseUnitId: $unitId,
            );

            $this->assertCreationRejected(
                $handler,
                $inactiveCategoryCommand,
                'La categoría no existe o está inactiva.'
            );

            $this->assertFalse(
                $repository->existsBySku(
                    Sku::fromString($inactiveCategoryCommand->sku)
                )
            );

            // Reactivar la categoría para evaluar la unidad por separado.
            DB::table('inventory_categories')
                ->where('id', $categoryId)
                ->update(['active' => true]);

            DB::table('inventory_units')
                ->where('id', $unitId)
                ->update(['active' => false]);

            $inactiveUnitCommand = new CreateProductCommand(
                sku: $prefix.'-UNIT',
                name: 'Producto con unidad inactiva',
                categoryId: $categoryId,
                baseUnitId: $unitId,
            );

            $this->assertCreationRejected(
                $handler,
                $inactiveUnitCommand,
                'La unidad base no existe o está inactiva.'
            );

            $this->assertFalse(
                $repository->existsBySku(
                    Sku::fromString($inactiveUnitCommand->sku)
                )
            );
        } finally {
            // Restaurar productos, categorías, unidades y datos del seeder.
            $connection->rollBack();
        }
    }

    private function assertCreationRejected(
        CreateProductHandler $handler,
        CreateProductCommand $command,
        string $expectedMessage,
    ): void {
        try {
            $handler->handle($command);
        } catch (ProductCreationException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );

            return;
        }

        $this->fail('La creación debía ser rechazada.');
    }
}
