<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence\Services;

use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use App\Modules\Inventory\Application\Ports\InventoryOperationsInterface;
use App\Modules\Sales\Application\Ports\SalesServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SqlSalesService implements SalesServiceInterface
{
    public function __construct(
        private readonly InventoryOperationsInterface $inventory,
        private readonly AccountingPostingInterface $accounting,
    ) {}

    public function create(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $key = strtolower((string) $data['operation_key']);
            $existing = DB::table('sales_documents')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                return ['sale_id' => (int) $existing->id, 'number' => $existing->number, 'repeated' => true];
            }

            $customer = DB::table('sales_customers')->where('id', (int) $data['customer_id'])->where('active', true)->first();
            if ($customer === null) {
                throw new DomainException('El cliente no está disponible.');
            }
            if (! DB::table('inventory_locations')->where('id', (int) $data['location_id'])->where('active', true)->exists()) {
                throw new DomainException('La ubicación no está disponible.');
            }

            $subtotal = BigDecimal::zero()->toScale(2);
            $discountTotal = BigDecimal::zero()->toScale(2);
            $taxTotal = BigDecimal::zero()->toScale(2);
            $grandTotal = BigDecimal::zero()->toScale(2);
            $prepared = [];

            foreach ($data['lines'] as $line) {
                $quantity = BigDecimal::of((string) $line['quantity']);
                $price = BigDecimal::of((string) $line['unit_price']);
                $discount = BigDecimal::of((string) ($line['discount'] ?? 0))->toScale(2, RoundingMode::HALF_UP);
                $rate = BigDecimal::of((string) ($line['tax_rate'] ?? 0));
                if (! $quantity->isGreaterThan('0') || $price->isLessThan('0') || $discount->isLessThan('0') || $rate->isLessThan('0')) {
                    throw new DomainException('Cantidades, precios, descuentos e impuestos deben ser válidos.');
                }
                $gross = $quantity->multipliedBy($price)->toScale(2, RoundingMode::HALF_UP);
                if ($discount->isGreaterThan($gross)) {
                    throw new DomainException('El descuento de una línea no puede superar su importe.');
                }
                $net = $gross->minus($discount);
                $tax = $net->multipliedBy($rate)->dividedBy('100', 2, RoundingMode::HALF_UP);
                $total = $net->plus($tax);
                $subtotal = $subtotal->plus($gross);
                $discountTotal = $discountTotal->plus($discount);
                $taxTotal = $taxTotal->plus($tax);
                $grandTotal = $grandTotal->plus($total);
                $prepared[] = [...$line, 'subtotal' => (string) $gross, 'discount' => (string) $discount, 'tax' => (string) $tax, 'total' => (string) $total];
            }

            $paid = BigDecimal::of((string) ($data['paid_amount'] ?? 0))->toScale(2, RoundingMode::HALF_UP);
            if ($paid->isLessThan('0') || $paid->isGreaterThan($grandTotal)) {
                throw new DomainException('El monto pagado debe estar entre cero y el total.');
            }
            if (($data['payment_type'] ?? 'cash') === 'cash' && ! $paid->isEqualTo($grandTotal)) {
                throw new DomainException('Una venta de contado debe quedar pagada totalmente.');
            }
            $treasury = ! empty($data['treasury_account_id'])
                ? DB::table('accounting_treasury_accounts')->where('id', (int) $data['treasury_account_id'])->where('active', true)->first()
                : null;
            if ($paid->isGreaterThan('0') && $treasury === null) {
                throw new DomainException('Selecciona la caja o cuenta bancaria que recibió el cobro.');
            }

            $sequence = (int) DB::table('sales_documents')->lockForUpdate()->max('id') + 1;
            $number = sprintf('VTA-%s-%06d', str_replace('-', '', (string) $data['document_date']), $sequence);
            $now = now();
            $saleId = DB::table('sales_documents')->insertGetId([
                'operation_key' => $key,
                'number' => $number,
                'document_date' => $data['document_date'],
                'customer_id' => $data['customer_id'],
                'location_id' => $data['location_id'],
                'status' => 'posted',
                'payment_type' => $data['payment_type'],
                'treasury_account_id' => $treasury?->id,
                'subtotal' => (string) $subtotal,
                'discount' => (string) $discountTotal,
                'tax' => (string) $taxTotal,
                'total' => (string) $grandTotal,
                'paid_amount' => (string) $paid,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'journal_entry_id' => null,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $totalCost = BigDecimal::zero()->toScale(2);
            foreach ($prepared as $index => $line) {
                $movement = $this->inventory->registerExit([
                    'operation_key' => (string) Str::uuid(),
                    'product_id' => $line['product_id'],
                    'location_id' => $data['location_id'],
                    'unit_id' => $line['unit_id'],
                    'reason' => 'sale',
                    'document_date' => $data['document_date'],
                    'reference' => $number,
                    'notes' => 'Salida automática por venta',
                    'quantity' => (string) $line['quantity'],
                ], $userId);
                $movementLine = DB::table('inventory_movement_lines')->where('movement_id', $movement['movement_id'])->first();
                if ($movementLine === null) {
                    throw new DomainException('No fue posible determinar el costo de la salida.');
                }
                $lineCost = BigDecimal::of((string) $movementLine->total_cost)->toScale(2, RoundingMode::HALF_UP);
                $totalCost = $totalCost->plus($lineCost);
                DB::table('sales_lines')->insert([
                    'sale_id' => $saleId,
                    'line_number' => $index + 1,
                    'product_id' => $line['product_id'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'subtotal' => $line['subtotal'],
                    'tax_amount' => $line['tax'],
                    'total' => $line['total'],
                    'unit_cost' => $movementLine->unit_cost,
                    'inventory_movement_id' => $movement['movement_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $entryId = $this->accounting->postSale($saleId, [
                'date' => $data['document_date'], 'number' => $number,
                'paid' => (string) $paid, 'total' => (string) $grandTotal,
                'net' => (string) $subtotal->minus($discountTotal),
                'tax' => (string) $taxTotal, 'cost' => (string) $totalCost,
                'treasury_accounting_id' => $treasury?->accounting_account_id,
            ], $userId);
            DB::table('sales_documents')->where('id', $saleId)->update(['journal_entry_id' => $entryId]);

            if ($paid->isGreaterThan('0')) {
                DB::table('sales_payments')->insert([
                    'sale_id' => $saleId, 'payment_date' => $data['document_date'], 'amount' => (string) $paid,
                    'method' => $data['payment_method'] ?? 'cash', 'reference' => null,
                    'treasury_account_id' => $treasury->id,
                    'notes' => 'Pago registrado con la venta', 'received_by' => $userId,
                    'journal_entry_id' => $entryId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            return ['sale_id' => (int) $saleId, 'number' => $number, 'repeated' => false];
        }, 5);
    }

    public function registerPayment(int $saleId, array $data, int $userId): int
    {
        return DB::transaction(function () use ($saleId, $data, $userId): int {
            $sale = DB::table('sales_documents')->where('id', $saleId)->lockForUpdate()->first();
            $treasury = DB::table('accounting_treasury_accounts')->where('id', (int) $data['treasury_account_id'])->where('active', true)->first();
            if ($sale === null || $sale->status !== 'posted' || $treasury === null) {
                throw new DomainException('La venta no está disponible para cobros.');
            }
            $amount = BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::HALF_UP);
            $balance = BigDecimal::of((string) $sale->total)->minus((string) $sale->paid_amount);
            if (! $amount->isGreaterThan('0') || $amount->isGreaterThan($balance)) {
                throw new DomainException('El cobro supera el saldo pendiente o no es válido.');
            }
            $now = now();
            $paymentId = DB::table('sales_payments')->insertGetId([
                'sale_id' => $saleId, 'payment_date' => $data['payment_date'], 'amount' => (string) $amount,
                'method' => $data['method'], 'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'treasury_account_id' => $treasury->id,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'received_by' => $userId,
                'journal_entry_id' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $entryId = $this->accounting->postPayment($paymentId, (string) $amount, $data['payment_date'], $userId, (int) $treasury->accounting_account_id);
            DB::table('sales_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId]);
            DB::table('sales_documents')->where('id', $saleId)->update([
                'paid_amount' => (string) BigDecimal::of((string) $sale->paid_amount)->plus($amount), 'updated_at' => $now,
            ]);
            return (int) $paymentId;
        }, 5);
    }
}
