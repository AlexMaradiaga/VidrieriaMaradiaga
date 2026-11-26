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
        $lines = [];
        foreach ($data['lines'] ?? [$data] as $index => $line) {
            try {
                $quantity = $this->inputDecimal($line['quantity'], __('inventory.fields.quantity'));
                $unitCost = $this->inputDecimal($line['unit_cost'], __('inventory.fields.unit_cost'));

                if (! $quantity->isGreaterThan('0')) {
                    throw new DomainException(__('inventory.errors.quantity_positive'));
                }

                $lines[] = [
                    'product_id' => (int) $line['product_id'],
                    'location_id' => (int) $line['location_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'quantity' => (string) $quantity,
                    'unit_cost' => (string) $unitCost,
                ];
            } catch (DomainException $exception) {
                throw $this->lineException($index + 1, $exception);
            }
        }

        if ($lines === []) {
            throw new DomainException(__('inventory.validation.required', ['attribute' => 'productos']));
        }

        $payload = [
            'supplier_id' => isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            'document_date' => $data['document_date'],
            'reason' => $data['reason'],
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;

        // Conservar la huella de las entradas de una línea y sus reintentos anteriores.
        $hashedPayload = count($lines) === 1
            ? [
                'user_id' => $userId,
                'product_id' => $lines[0]['product_id'],
                'location_id' => $lines[0]['location_id'],
                'unit_id' => $lines[0]['unit_id'],
                ...$payload,
                'quantity' => $lines[0]['quantity'],
                'unit_cost' => $lines[0]['unit_cost'],
            ]
            : ['user_id' => $userId, ...$payload, 'category_id' => $categoryId, 'lines' => $lines];

        $operationKey = strtolower($data['operation_key']);
        $hash = hash('sha256', json_encode($hashedPayload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($payload, $lines, $operationKey, $hash, $userId, $categoryId): array {
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
                    throw new DomainException(__('inventory.errors.operation_key_used'));
                }

                return [
                    'movement_id' => (int) $existing->id,
                    'repeated' => true,
                ];
            }

            if (
                $payload['reason'] === 'purchase'
                && $payload['supplier_id'] === null
            ) {
                throw new DomainException(__('inventory.errors.supplier_required'));
            }

            if ($payload['supplier_id'] !== null) {
                $supplierAvailable = DB::table('inventory_suppliers')
                    ->where('id', $payload['supplier_id'])
                    ->where('active', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $supplierAvailable) {
                    throw new DomainException(__('inventory.errors.supplier_unavailable'));
                }
            }

            // Bloquear todos los productos en el mismo orden antes de tocar existencias.
            $productIds = array_unique(array_column($lines, 'product_id'));
            sort($productIds, SORT_NUMERIC);
            foreach ($productIds as $productId) {
                DB::table('inventory_products')
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first(['id']);
            }

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

            foreach ($lines as $index => $line) {
                try {
                    $this->registerLine(
                        [...$payload, ...$line],
                        (int) $movementId,
                        $index + 1,
                        $now,
                        $categoryId,
                    );
                } catch (DomainException $exception) {
                    throw $this->lineException($index + 1, $exception);
                }
            }

            return [
                'movement_id' => (int) $movementId,
                'repeated' => false,
            ];
        }, 5);
    }

    private function registerLine(
        array $payload,
        int $movementId,
        int $lineNumber,
        \DateTimeInterface $now,
        ?int $categoryId,
    ): void {
        $quantity = BigDecimal::of($payload['quantity']);
        $unitCost = BigDecimal::of($payload['unit_cost']);

        // Todas las operaciones de stock deben bloquear primero el producto.
        $product = DB::table('inventory_products')
            ->where('id', $payload['product_id'])
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($product === null || ! $product->active) {
            throw new DomainException(__('inventory.errors.product_inactive'));
        }

        if ($categoryId !== null && (int) $product->category_id !== $categoryId) {
            throw new DomainException('El producto no pertenece a la categoría seleccionada.');
        }

        if (! $product->track_stock) {
            throw new DomainException(__('inventory.errors.stock_disabled'));
        }

        if ($product->track_lots || $product->track_remnants) {
            throw new DomainException(__('inventory.errors.detail_unsupported'));
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
            throw new DomainException(__('inventory.errors.location_unavailable'));
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
            throw new DomainException(__('inventory.errors.units_unavailable'));
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
                throw new DomainException(__('inventory.errors.purchase_conversion_missing'));
            }

            $factor = BigDecimal::of(
                (string) $conversion->conversion_factor_to_base
            );
        }

        if (! $factor->isGreaterThan('0')) {
            throw new DomainException(__('inventory.errors.invalid_conversion'));
        }

        $exactBaseQuantity = $quantity->multipliedBy($factor);

        $baseQuantity = $exactBaseQuantity->toScale(
            4,
            RoundingMode::HALF_UP
        );

        // No perder material por redondear silenciosamente una conversión.
        if (! $exactBaseQuantity->isEqualTo($baseQuantity)) {
            throw new DomainException(__('inventory.errors.conversion_quantity_precision'));
        }

        $this->assertFits($baseQuantity, 18, 4, __('inventory.fields.converted_quantity'));

        $totalCost = $quantity
            ->multipliedBy($unitCost)
            ->toScale(4, RoundingMode::HALF_UP);

        $this->assertFits($totalCost, 28, 4, __('inventory.fields.amount'));

        // Usar el importe registrado para mantener coherente la valoración.
        $baseUnitCost = $totalCost->dividedBy(
            $baseQuantity,
            8,
            RoundingMode::HALF_UP
        );

        $this->assertFits($baseUnitCost, 18, 8, __('inventory.fields.base_unit_cost'));

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
            throw new DomainException(__('inventory.errors.negative_global_balance'));
        }

        $newLocationQuantity = $locationQuantity
            ->plus($baseQuantity)
            ->toScale(4);

        $this->assertFits(
            $newLocationQuantity,
            18,
            4,
            __('inventory.fields.location_stock')
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

        $this->assertFits($newAverage, 18, 4, __('inventory.fields.average_cost'));

        $lastPurchaseCost = $baseUnitCost->toScale(
            4,
            RoundingMode::HALF_UP
        );

        $this->assertFits(
            $lastPurchaseCost,
            18,
            4,
            __('inventory.fields.last_purchase_cost')
        );

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
            'line_number' => $lineNumber,
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
    }

    private function lineException(int $lineNumber, DomainException $exception): DomainException
    {
        return new DomainException("Línea {$lineNumber}: {$exception->getMessage()}", 0, $exception);
    }

    private function inputDecimal(string $value, string $field): BigDecimal
    {
        $value = trim($value);

        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $value)) {
            throw new DomainException(
                __('inventory.errors.invalid_decimal', ['field' => $field])
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
                __('inventory.errors.capacity', ['field' => $field])
            );
        }
    }
}
