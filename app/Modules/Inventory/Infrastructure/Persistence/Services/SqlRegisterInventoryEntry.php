<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Services;

use App\Modules\Inventory\Application\Ports\RegisterInventoryEntryInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SqlRegisterInventoryEntry implements RegisterInventoryEntryInterface
{
    public function register(array $data, int $userId): array
    {
        $quantity = $this->inputDecimal($data['quantity'], 'cantidad');
        $unitCost = $this->inputDecimal($data['unit_cost'], 'costo unitario');

        if (! $quantity->isGreaterThan('0')) {
            throw new DomainException('La cantidad debe ser mayor que cero.');
        }

        // Orden fijo de campos para obtener una huella reproducible.
        $payload = [
            'user_id' => $userId,
            'product_id' => (int) $data['product_id'],
            'location_id' => (int) $data['location_id'],
            'unit_id' => (int) $data['unit_id'],
            'supplier_id' => isset($data['supplier_id'])
                ? (int) $data['supplier_id']
                : null,
            'document_date' => $data['document_date'],
            'reason' => $data['reason'],
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'quantity' => (string) $quantity,
            'unit_cost' => (string) $unitCost,
        ];

        $operationKey = strtolower($data['operation_key']);

        $hash = hash(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        return DB::transaction(function () use (
            $payload,
            $operationKey,
            $hash,
            $quantity,
            $unitCost,
            $userId,
        ): array {
            // La lectura bloqueada protege también la comprobación de reintentos.
            $existing = DB::table('inventory_movements')
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->request_hash !== $hash
                    || $existing->direction !== 'inbound'
                    || $existing->status !== 'posted'
                ) {
                    throw new DomainException(
                        'El identificador de solicitud ya pertenece a otra operación.'
                    );
                }

                return [
                    'movement_id' => (int) $existing->id,
                    'repeated' => true,
                ];
            }

            // Todas las operaciones de stock deben bloquear primero el producto.
            $product = DB::table('inventory_products')
                ->where('id', $payload['product_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($product === null || ! $product->active) {
                throw new DomainException(
                    'El producto no existe o está inactivo.'
                );
            }

            if (! $product->track_stock) {
                throw new DomainException(
                    'Este producto no tiene habilitado el control de existencias.'
                );
            }

            if ($product->track_lots || $product->track_remnants) {
                throw new DomainException(
                    'Este producto requiere detalle de lotes o retazos. '
                    .'Ese detalle todavía no está disponible en este flujo.'
                );
            }

            $location = DB::table('inventory_locations as locations')
                ->join(
                    'inventory_warehouses as warehouses',
                    'warehouses.id',
                    '=',
                    'locations.warehouse_id'
                )
                ->where('locations.id', $payload['location_id'])
                ->where('locations.active', true)
                ->whereNull('locations.deleted_at')
                ->where('warehouses.active', true)
                ->whereNull('warehouses.deleted_at')
                ->exists();

            if (! $location) {
                throw new DomainException(
                    'La ubicación o su bodega no están disponibles.'
                );
            }

            $unitAvailable = DB::table('inventory_units')
                ->where('id', $payload['unit_id'])
                ->where('active', true)
                ->whereNull('deleted_at')
                ->exists();

            $baseUnitAvailable = DB::table('inventory_units')
                ->where('id', $product->base_unit_id)
                ->where('active', true)
                ->whereNull('deleted_at')
                ->exists();

            if (! $unitAvailable || ! $baseUnitAvailable) {
                throw new DomainException(
                    'La unidad de entrada o la unidad base no están disponibles.'
                );
            }

            if (
                $payload['reason'] === 'purchase'
                && $payload['supplier_id'] === null
            ) {
                throw new DomainException(
                    'Selecciona un proveedor para registrar una compra.'
                );
            }

            if ($payload['supplier_id'] !== null) {
                $supplierAvailable = DB::table('inventory_suppliers')
                    ->where('id', $payload['supplier_id'])
                    ->where('active', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $supplierAvailable) {
                    throw new DomainException(
                        'El proveedor no existe o está inactivo.'
                    );
                }
            }

            $factor = BigDecimal::of('1');

            if ($payload['unit_id'] !== (int) $product->base_unit_id) {
                $conversion = DB::table('inventory_product_units')
                    ->where('product_id', $product->id)
                    ->where('unit_id', $payload['unit_id'])
                    ->where('active', true)
                    ->where('is_purchase_unit', true)
                    ->first();

                if ($conversion === null) {
                    throw new DomainException(
                        'La unidad seleccionada no tiene una conversión de compra configurada.'
                    );
                }

                $factor = BigDecimal::of(
                    (string) $conversion->conversion_factor_to_base
                );
            }

            if (! $factor->isGreaterThan('0')) {
                throw new DomainException('El factor de conversión no es válido.');
            }

            $exactBaseQuantity = $quantity->multipliedBy($factor);

            $baseQuantity = $exactBaseQuantity->toScale(
                4,
                RoundingMode::HALF_UP
            );

            // No perder material por redondear silenciosamente una conversión.
            if (! $exactBaseQuantity->isEqualTo($baseQuantity)) {
                throw new DomainException(
                    'La conversión requiere más de cuatro decimales de cantidad. '
                    .'Revisa la unidad de control o la cantidad ingresada.'
                );
            }

            $this->assertFits($baseQuantity, 18, 4, 'cantidad convertida');

            $totalCost = $quantity
                ->multipliedBy($unitCost)
                ->toScale(4, RoundingMode::HALF_UP);

            $this->assertFits($totalCost, 28, 4, 'importe');

            // Usar el importe registrado para mantener coherente la valoración.
            $baseUnitCost = $totalCost->dividedBy(
                $baseQuantity,
                8,
                RoundingMode::HALF_UP
            );

            $this->assertFits($baseUnitCost, 18, 8, 'costo por unidad base');

            $balance = DB::table('inventory_stock_balances')
                ->where('product_id', $product->id)
                ->where('location_id', $payload['location_id'])
                ->lockForUpdate()
                ->first();

            $locationQuantity = BigDecimal::of(
                $balance === null ? '0' : (string) $balance->quantity
            );

            $globalQuantity = BigDecimal::of(
                (string) DB::table('inventory_stock_balances')
                    ->where('product_id', $product->id)
                    ->sum('quantity')
            );

            if ($globalQuantity->isLessThan('0')) {
                throw new DomainException(
                    'El producto tiene saldo global negativo. '
                    .'Su regularización requiere un flujo de valoración específico.'
                );
            }

            $newLocationQuantity = $locationQuantity
                ->plus($baseQuantity)
                ->toScale(4);

            $this->assertFits(
                $newLocationQuantity,
                18,
                4,
                'existencia de la ubicación'
            );

            $newGlobalQuantity = $globalQuantity->plus($baseQuantity);

            $currentAverage = BigDecimal::of(
                (string) $product->average_cost
            );

            $newAverage = $globalQuantity
                ->multipliedBy($currentAverage)
                ->plus($totalCost)
                ->dividedBy(
                    $newGlobalQuantity,
                    4,
                    RoundingMode::HALF_UP
                );

            $this->assertFits($newAverage, 18, 4, 'costo promedio');

            $lastPurchaseCost = $baseUnitCost->toScale(
                4,
                RoundingMode::HALF_UP
            );

            $this->assertFits(
                $lastPurchaseCost,
                18,
                4,
                'último costo de compra'
            );

            $now = now();

            $movementId = DB::table('inventory_movements')->insertGetId([
                'operation_key' => $operationKey,
                'request_hash' => $hash,
                'direction' => 'inbound',
                'reason' => $payload['reason'],
                'status' => 'posted',
                'document_date' => $payload['document_date'],
                'reference' => $payload['reference'],
                'notes' => $payload['notes'],
                'supplier_id' => $payload['supplier_id'],
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_stock_balances')->updateOrInsert(
                [
                    'product_id' => $product->id,
                    'location_id' => $payload['location_id'],
                ],
                [
                    'quantity' => (string) $newLocationQuantity,
                    'created_at' => $balance?->created_at ?? $now,
                    'updated_at' => $now,
                ]
            );

            $productChanges = [
                'average_cost' => (string) $newAverage,
                'updated_at' => $now,
            ];

            if ($payload['reason'] === 'purchase') {
                $productChanges['last_purchase_cost'] = (string) $lastPurchaseCost;
            }

            DB::table('inventory_products')
                ->where('id', $product->id)
                ->update($productChanges);

            DB::table('inventory_movement_lines')->insert([
                'movement_id' => $movementId,
                'line_number' => 1,
                'product_id' => $product->id,
                'location_id' => $payload['location_id'],
                'unit_id' => $payload['unit_id'],
                'base_unit_id' => $product->base_unit_id,
                'quantity' => (string) $quantity,
                'conversion_factor' => (string) $factor->toScale(8),
                'base_quantity' => (string) $baseQuantity,
                'unit_cost' => (string) $unitCost,
                'base_unit_cost' => (string) $baseUnitCost,
                'total_cost' => (string) $totalCost,
                'balance_after' => (string) $newLocationQuantity,
                'average_cost_after' => (string) $newAverage,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'movement_id' => (int) $movementId,
                'repeated' => false,
            ];
        }, 5);
    }

    private function inputDecimal(string $value, string $field): BigDecimal
    {
        $value = trim($value);

        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $value)) {
            throw new DomainException(
                "El campo {$field} debe tener hasta cuatro decimales y no ser negativo."
            );
        }

        return BigDecimal::of($value)->toScale(4);
    }

    private function assertFits(
        BigDecimal $value,
        int $precision,
        int $scale,
        string $field,
    ): void {
        $limit = BigDecimal::of(
            str_repeat('9', $precision - $scale)
            .'.'
            .str_repeat('9', $scale)
        );

        if ($value->abs()->isGreaterThan($limit)) {
            throw new DomainException(
                "El campo {$field} supera la capacidad permitida."
            );
        }
    }
}
