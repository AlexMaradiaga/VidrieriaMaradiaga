<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Services;

use App\Modules\Inventory\Application\Ports\InventoryOperationsInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SqlInventoryOperations implements InventoryOperationsInterface
{
    public function registerExit(array $data, int $userId): array
    {
        $quantity = $this->decimal($data['quantity'], __('inventory.fields.quantity'));

        if (! $quantity->isGreaterThan('0')) {
            throw new DomainException(__('inventory.errors.quantity_positive'));
        }

        $payload = [
            'product_id' => (int) $data['product_id'],
            'location_id' => (int) $data['location_id'],
            'unit_id' => (int) $data['unit_id'],
            'reason' => $data['reason'],
            'document_date' => $data['document_date'],
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'quantity' => (string) $quantity,
            'user_id' => $userId,
        ];
        $key = strtolower($data['operation_key']);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($payload, $key, $hash, $quantity, $userId): array {
            $existing = DB::table('inventory_movements')->where('operation_key', $key)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->request_hash !== $hash || $existing->direction !== 'outbound' || $existing->status !== 'posted') {
                    throw new DomainException(__('inventory.errors.operation_key_used'));
                }

                return ['movement_id' => (int) $existing->id, 'repeated' => true];
            }

            $product = $this->product($payload['product_id']);
            $factor = $this->issueFactor($product, $payload['unit_id']);
            $baseQuantity = $this->convertedQuantity($quantity, $factor);
            $balance = $this->balance($product->id, $payload['location_id']);
            $current = BigDecimal::of($balance === null ? '0' : (string) $balance->quantity)->toScale(4);

            if ($current->isLessThan($baseQuantity)) {
                throw new DomainException(__('inventory.errors.insufficient_stock'));
            }

            $after = $current->minus($baseQuantity)->toScale(4);
            $average = BigDecimal::of((string) $product->average_cost)->toScale(4);
            $baseUnitCost = $average->toScale(8);
            $unitCost = $baseUnitCost->multipliedBy($factor)->toScale(4, RoundingMode::HALF_UP);
            $totalCost = $average->multipliedBy($baseQuantity)->toScale(4, RoundingMode::HALF_UP);
            $now = now();

            $movementId = DB::table('inventory_movements')->insertGetId([
                'operation_key' => $key,
                'request_hash' => $hash,
                'direction' => 'outbound',
                'reason' => $payload['reason'],
                'status' => 'posted',
                'document_date' => $payload['document_date'],
                'reference' => $payload['reference'],
                'notes' => $payload['notes'],
                'supplier_id' => null,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->saveBalance($product->id, $payload['location_id'], $after, $balance, $now);
            $this->insertLine($movementId, $product, $payload['location_id'], $payload['unit_id'], $quantity, $factor, $baseQuantity, $unitCost, $baseUnitCost, $totalCost, $after, $average, $now);

            return ['movement_id' => (int) $movementId, 'repeated' => false];
        }, 5);
    }

    public function transfer(array $data, int $userId): array
    {
        $quantity = $this->decimal($data['quantity'], __('inventory.fields.quantity'));

        if (! $quantity->isGreaterThan('0')) {
            throw new DomainException(__('inventory.errors.quantity_positive'));
        }

        $source = (int) $data['source_location_id'];
        $destination = (int) $data['destination_location_id'];

        if ($source === $destination) {
            throw new DomainException(__('inventory.errors.same_locations'));
        }

        $key = strtolower($data['operation_key']);
        $requestHash = hash('sha256', json_encode([
            'user_id' => $userId,
            'product_id' => (int) $data['product_id'],
            'source_location_id' => $source,
            'destination_location_id' => $destination,
            'quantity' => (string) $quantity,
            'document_date' => $data['document_date'],
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $userId, $quantity, $source, $destination, $key, $requestHash): array {
            $existing = DB::table('inventory_transfers')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_hash !== $requestHash) {
                    throw new DomainException(__('inventory.errors.operation_key_used'));
                }
                return ['transfer_id' => (int) $existing->id, 'repeated' => true];
            }

            $product = $this->product((int) $data['product_id']);
            $this->assertLocation($source);
            $this->assertLocation($destination);

            $balances = [];
            $locationIds = [$source, $destination];
            sort($locationIds);
            foreach ($locationIds as $locationId) {
                $balances[$locationId] = $this->balance($product->id, $locationId);
            }

            $sourceQuantity = BigDecimal::of($balances[$source] === null ? '0' : (string) $balances[$source]->quantity)->toScale(4);
            if ($sourceQuantity->isLessThan($quantity)) {
                throw new DomainException(__('inventory.errors.source_insufficient_stock'));
            }

            $destinationQuantity = BigDecimal::of($balances[$destination] === null ? '0' : (string) $balances[$destination]->quantity)->toScale(4);
            $sourceAfter = $sourceQuantity->minus($quantity)->toScale(4);
            $destinationAfter = $destinationQuantity->plus($quantity)->toScale(4);
            $average = BigDecimal::of((string) $product->average_cost)->toScale(4);
            $baseUnitCost = $average->toScale(8);
            $total = $average->multipliedBy($quantity)->toScale(4, RoundingMode::HALF_UP);
            $now = now();
            $reference = trim((string) ($data['reference'] ?? '')) ?: null;
            $notes = trim((string) ($data['notes'] ?? '')) ?: null;

            $out = $this->movement('outbound', 'transfer', $data['document_date'], $reference, $notes, $userId, $now);
            $in = $this->movement('inbound', 'transfer', $data['document_date'], $reference, $notes, $userId, $now);

            $this->saveBalance($product->id, $source, $sourceAfter, $balances[$source], $now);
            $this->saveBalance($product->id, $destination, $destinationAfter, $balances[$destination], $now);
            $this->insertLine($out, $product, $source, $product->base_unit_id, $quantity, BigDecimal::of('1'), $quantity, $average, $baseUnitCost, $total, $sourceAfter, $average, $now);
            $this->insertLine($in, $product, $destination, $product->base_unit_id, $quantity, BigDecimal::of('1'), $quantity, $average, $baseUnitCost, $total, $destinationAfter, $average, $now);

            $transferId = DB::table('inventory_transfers')->insertGetId([
                'operation_key' => $key,
                'request_hash' => $requestHash,
                'product_id' => $product->id,
                'source_location_id' => $source,
                'destination_location_id' => $destination,
                'quantity' => (string) $quantity,
                'document_date' => $data['document_date'],
                'reference' => $reference,
                'notes' => $notes,
                'outbound_movement_id' => $out,
                'inbound_movement_id' => $in,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['transfer_id' => (int) $transferId, 'repeated' => false];
        }, 5);
    }

    public function registerCount(array $data, int $userId): array
    {
        $key = strtolower($data['operation_key']);
        $locationId = (int) $data['location_id'];
        $lines = collect($data['lines'])->sortBy('product_id')->values();
        $requestHash = hash('sha256', json_encode([
            'user_id' => $userId,
            'location_id' => $locationId,
            'document_date' => $data['document_date'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'lines' => $lines->map(fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'counted_quantity' => (string) $this->decimal($line['counted_quantity'], __('inventory.fields.counted_quantity')),
            ])->all(),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $userId, $key, $locationId, $lines, $requestHash): array {
            $existing = DB::table('inventory_counts')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_hash !== $requestHash) {
                    throw new DomainException(__('inventory.errors.operation_key_used'));
                }
                return ['count_id' => (int) $existing->id, 'repeated' => true];
            }

            $this->assertLocation($locationId);
            $now = now();
            $countId = DB::table('inventory_counts')->insertGetId([
                'operation_key' => $key,
                'request_hash' => $requestHash,
                'location_id' => $locationId,
                'document_date' => $data['document_date'],
                'status' => 'posted',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $product = $this->product((int) $line['product_id']);
                $counted = $this->decimal($line['counted_quantity'], __('inventory.fields.counted_quantity'));
                $balance = $this->balance($product->id, $locationId);
                $expected = BigDecimal::of($balance === null ? '0' : (string) $balance->quantity)->toScale(4);
                $variance = $counted->minus($expected)->toScale(4);
                $average = BigDecimal::of((string) $product->average_cost)->toScale(4);
                $movementId = null;

                if (! $variance->isEqualTo('0')) {
                    $direction = $variance->isGreaterThan('0') ? 'inbound' : 'outbound';
                    $absolute = $variance->abs();
                    $movementId = $this->movement($direction, 'adjustment', $data['document_date'], 'COUNT-'.$countId, __('inventory.count_adjustment_note'), $userId, $now);
                    $total = $average->multipliedBy($absolute)->toScale(4, RoundingMode::HALF_UP);
                    $this->insertLine($movementId, $product, $locationId, $product->base_unit_id, $absolute, BigDecimal::of('1'), $absolute, $average, $average->toScale(8), $total, $counted, $average, $now);
                }

                $this->saveBalance($product->id, $locationId, $counted, $balance, $now);
                DB::table('inventory_count_lines')->insert([
                    'count_id' => $countId,
                    'product_id' => $product->id,
                    'expected_quantity' => (string) $expected,
                    'counted_quantity' => (string) $counted,
                    'variance_quantity' => (string) $variance,
                    'unit_cost' => (string) $average,
                    'movement_id' => $movementId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['count_id' => (int) $countId, 'repeated' => false];
        }, 5);
    }

    private function product(int $id): object
    {
        $product = DB::table('inventory_products')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
        if ($product === null || ! $product->active || ! $product->track_stock) {
            throw new DomainException(__('inventory.errors.product_unavailable'));
        }

        // SQL Server puede devolver columnas BIGINT como cadenas. Normalizar
        // los identificadores aquí evita errores de tipo en los métodos
        // internos y mantiene una única frontera de conversión.
        $product->id = (int) $product->id;
        $product->base_unit_id = (int) $product->base_unit_id;

        return $product;
    }

    private function issueFactor(object $product, int $unitId): BigDecimal
    {
        if ($unitId === (int) $product->base_unit_id) {
            return BigDecimal::of('1');
        }

        $factor = DB::table('inventory_product_units')
            ->where('product_id', $product->id)->where('unit_id', $unitId)
            ->where('active', true)->where('is_issue_unit', true)
            ->value('conversion_factor_to_base');

        if ($factor === null) {
            throw new DomainException(__('inventory.errors.issue_conversion_missing'));
        }

        return BigDecimal::of((string) $factor);
    }

    private function convertedQuantity(BigDecimal $quantity, BigDecimal $factor): BigDecimal
    {
        $exact = $quantity->multipliedBy($factor);
        $rounded = $exact->toScale(4, RoundingMode::HALF_UP);
        if (! $exact->isEqualTo($rounded)) {
            throw new DomainException(__('inventory.errors.conversion_precision'));
        }

        return $rounded;
    }

    private function assertLocation(int $id): void
    {
        $exists = DB::table('inventory_locations as locations')
            ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
            ->where('locations.id', $id)->where('locations.active', true)->whereNull('locations.deleted_at')
            ->where('warehouses.active', true)->whereNull('warehouses.deleted_at')->exists();
        if (! $exists) {
            throw new DomainException(__('inventory.errors.location_unavailable'));
        }
    }

    private function balance(int $productId, int $locationId): ?object
    {
        $this->assertLocation($locationId);

        return DB::table('inventory_stock_balances')
            ->where('product_id', $productId)->where('location_id', $locationId)
            ->lockForUpdate()->first();
    }

    private function saveBalance(int $productId, int $locationId, BigDecimal $quantity, ?object $balance, mixed $now): void
    {
        if ($quantity->isLessThan('0')) {
            throw new DomainException(__('inventory.errors.negative_stock'));
        }

        DB::table('inventory_stock_balances')->updateOrInsert(
            ['product_id' => $productId, 'location_id' => $locationId],
            ['quantity' => (string) $quantity->toScale(4), 'created_at' => $balance?->created_at ?? $now, 'updated_at' => $now],
        );
    }

    private function movement(string $direction, string $reason, string $date, ?string $reference, ?string $notes, int $userId, mixed $now): int
    {
        return (int) DB::table('inventory_movements')->insertGetId([
            'operation_key' => (string) Str::uuid(),
            'request_hash' => null,
            'direction' => $direction,
            'reason' => $reason,
            'status' => 'posted',
            'document_date' => $date,
            'reference' => $reference,
            'notes' => $notes,
            'supplier_id' => null,
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertLine(int $movementId, object $product, int $locationId, int $unitId, BigDecimal $quantity, BigDecimal $factor, BigDecimal $baseQuantity, BigDecimal $unitCost, BigDecimal $baseUnitCost, BigDecimal $totalCost, BigDecimal $after, BigDecimal $average, mixed $now): void
    {
        DB::table('inventory_movement_lines')->insert([
            'movement_id' => $movementId,
            'line_number' => 1,
            'product_id' => $product->id,
            'location_id' => $locationId,
            'unit_id' => $unitId,
            'base_unit_id' => $product->base_unit_id,
            'quantity' => (string) $quantity->toScale(4),
            'conversion_factor' => (string) $factor->toScale(8),
            'base_quantity' => (string) $baseQuantity->toScale(4),
            'unit_cost' => (string) $unitCost->toScale(4, RoundingMode::HALF_UP),
            'base_unit_cost' => (string) $baseUnitCost->toScale(8, RoundingMode::HALF_UP),
            'total_cost' => (string) $totalCost->toScale(4, RoundingMode::HALF_UP),
            'balance_after' => (string) $after->toScale(4),
            'average_cost_after' => (string) $average->toScale(4),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function decimal(string $value, string $field): BigDecimal
    {
        $value = trim($value);
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $value)) {
            throw new DomainException(__('inventory.errors.invalid_decimal', ['field' => $field]));
        }

        return BigDecimal::of($value)->toScale(4);
    }
}
