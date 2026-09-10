<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Domain\Entities\Product;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\Sku;
use Database\Seeders\InventoryCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProductRepositoryTest extends TestCase
{
    public function test_it_persists_updates_and_soft_deletes_a_product(): void
    {
        // Verificar el destino real antes de realizar cualquier escritura.
        $this->assertTrue(app()->environment('testing'));

        $connection = DB::connection();

        $this->assertSame('sqlsrv', $connection->getDriverName());

        $database = $connection
            ->selectOne('SELECT DB_NAME() AS database_name');

        $this->assertSame(
            'DB_VidrieriaMaradiaga_Test',
            $database->database_name,
            'La prueba solamente puede ejecutarse en la base de pruebas.'
        );

        $connection->beginTransaction();

        try {
            $this->seed(InventoryCatalogSeeder::class);

            // Obtener IDs existentes; no asumir que comienzan en 1.
            $categoryId = DB::table('inventory_categories')
                ->orderBy('id')
                ->value('id');

            $baseUnitId = DB::table('inventory_units')
                ->orderBy('id')
                ->value('id');

            $this->assertNotNull($categoryId);
            $this->assertNotNull($baseUnitId);

            $repository = app(ProductRepositoryInterface::class);

            $sku = Sku::fromString(
                'TEST-'.strtoupper(bin2hex(random_bytes(8)))
            );

            $product = Product::create(
                sku: $sku,
                name: 'Perfil de prueba',
                categoryId: (int) $categoryId,
                baseUnitId: (int) $baseUnitId,
                minimumStock: '2.5000',
                maximumStock: null,
                averageCost: '12345678901234.1234',
                lastPurchaseCost: '150.2500',
                salePrice: '200.9900',
                trackStock: true,
                trackLots: false,
                trackRemnants: true,
                allowNegativeStock: false,
            );

            // Guardar: SQL Server debe asignar un ID.
            $saved = $repository->save($product);

            $this->assertNotNull($saved->id());
            $this->assertGreaterThan(0, $saved->id());

            // Leer nuevamente desde SQL Server.
            $loaded = $repository->findById($saved->id());

            $this->assertNotNull($loaded);
            $this->assertSame('Perfil de prueba', $loaded->name());
            $this->assertTrue($loaded->sku()->equals($sku));

            // Comprobar precisión, nulos y booleanos.
            $this->assertSame(
                '12345678901234.1234',
                $loaded->averageCost()
            );

            $this->assertSame('2.5000', $loaded->minimumStock());
            $this->assertNull($loaded->maximumStock());
            $this->assertNull($loaded->barcode());
            $this->assertTrue($loaded->tracksRemnants());
            $this->assertFalse($loaded->tracksLots());

            // Buscar por SKU y excluir el propio ID al validar duplicados.
            $foundBySku = $repository->findBySku($sku);

            $this->assertNotNull($foundBySku);
            $this->assertSame($saved->id(), $foundBySku->id());
            $this->assertTrue($repository->existsBySku($sku));

            $this->assertFalse(
                $repository->existsBySku($sku, $saved->id())
            );

            // Actualizar y volver a leer.
            $loaded->changeCommercialValues(
                averageCost: '160.1250',
                lastPurchaseCost: '165.7500',
                salePrice: '250.1234',
            );

            $loaded->deactivate();

            $updated = $repository->save($loaded);
            $reloaded = $repository->findById($updated->id());

            $this->assertNotNull($reloaded);
            $this->assertSame($saved->id(), $reloaded->id());
            $this->assertSame('160.1250', $reloaded->averageCost());
            $this->assertSame('250.1234', $reloaded->salePrice());
            $this->assertFalse($reloaded->isActive());

            // Eliminar lógicamente.
            $repository->delete($reloaded);

            $this->assertNull(
                $repository->findById($saved->id())
            );

            $this->assertNull($repository->findBySku($sku));

            // El registro sigue en SQL Server con deleted_at informado.
            $record = DB::table('inventory_products')
                ->where('id', $saved->id())
                ->first();

            $this->assertNotNull($record);
            $this->assertNotNull($record->deleted_at);

            // El SKU permanece reservado por la restricción UNIQUE.
            $this->assertTrue($repository->existsBySku($sku));
        } finally {
            // Revertir los cambios incluso si una comprobación falla.
            $connection->rollBack();
        }
    }
}
