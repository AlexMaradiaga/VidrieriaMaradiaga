<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Services;

use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use App\Modules\Inventory\Application\Ports\RegisterInventoryEntryInterface;
use App\Modules\Purchasing\Application\Ports\PurchaseServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SqlPurchaseService implements PurchaseServiceInterface
{
    public function __construct(
        private readonly RegisterInventoryEntryInterface $inventory,
        private readonly AccountingPostingInterface $accounting,
    ) {}

    public function create(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $key = strtolower((string) $data['operation_key']);
            $existing = DB::table('purchasing_documents')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                return ['purchase_id' => (int) $existing->id, 'number' => $existing->number, 'repeated' => true];
            }

            if (! DB::table('inventory_suppliers')->where('id', (int) $data['supplier_id'])->where('active', true)->exists()) {
                throw new DomainException('El proveedor no está disponible.');
            }

            $treasury = null;
            if (! empty($data['treasury_account_id'])) {
                $treasury = DB::table('accounting_treasury_accounts')->where('id', (int) $data['treasury_account_id'])->where('active', true)->first();
            }

            $subtotal = BigDecimal::zero()->toScale(2);
            $discountTotal = BigDecimal::zero()->toScale(2);
            $taxTotal = BigDecimal::zero()->toScale(2);
            $grandTotal = BigDecimal::zero()->toScale(2);
            $prepared = [];

            foreach ($data['lines'] as $line) {
                $quantity = BigDecimal::of((string) $line['quantity']);
                $cost = BigDecimal::of((string) $line['unit_cost']);
                $discount = BigDecimal::of((string) ($line['discount'] ?? 0))->toScale(2, RoundingMode::HALF_UP);
                $rate = BigDecimal::of((string) ($line['tax_rate'] ?? 0));
                $gross = $quantity->multipliedBy($cost)->toScale(2, RoundingMode::HALF_UP);
                if (! $quantity->isGreaterThan('0') || $cost->isLessThan('0') || $discount->isLessThan('0') || $discount->isGreaterThan($gross)) {
                    throw new DomainException('Revisa las cantidades, costos y descuentos de la compra.');
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
                throw new DomainException('El pago inicial no puede superar el total de la compra.');
            }
            if (($data['payment_type'] ?? 'credit') === 'cash' && ! $paid->isEqualTo($grandTotal)) {
                throw new DomainException('Una compra de contado debe quedar pagada completamente.');
            }
            if ($paid->isGreaterThan('0') && $treasury === null) {
                throw new DomainException('Selecciona la caja o cuenta bancaria desde la que se pagó.');
            }

            $sequence = (int) DB::table('purchasing_documents')->lockForUpdate()->max('id') + 1;
            $number = sprintf('COM-%s-%06d', str_replace('-', '', (string) $data['document_date']), $sequence);
            $now = now();
            $purchaseId = DB::table('purchasing_documents')->insertGetId([
                'operation_key' => $key, 'number' => $number,
                'supplier_document' => trim((string) ($data['supplier_document'] ?? '')) ?: null,
                'document_date' => $data['document_date'], 'due_date' => ($data['due_date'] ?? null) ?: null,
                'supplier_id' => $data['supplier_id'], 'location_id' => $data['location_id'],
                'status' => 'posted', 'payment_type' => $data['payment_type'],
                'treasury_account_id' => $treasury?->id,
                'subtotal' => (string) $subtotal, 'discount' => (string) $discountTotal,
                'tax' => (string) $taxTotal, 'total' => (string) $grandTotal, 'paid_amount' => (string) $paid,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'journal_entry_id' => null,
                'created_by' => $userId, 'posted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($prepared as $index => $line) {
                $movement = $this->inventory->register([
                    'operation_key' => (string) Str::uuid(), 'product_id' => $line['product_id'],
                    'location_id' => $data['location_id'], 'unit_id' => $line['unit_id'],
                    'supplier_id' => $data['supplier_id'], 'reason' => 'purchase',
                    'document_date' => $data['document_date'], 'reference' => $data['supplier_document'] ?: $number,
                    'notes' => 'Entrada automática por compra '.$number, 'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ], $userId);

                DB::table('purchasing_lines')->insert([
                    'purchase_id' => $purchaseId, 'line_number' => $index + 1,
                    'product_id' => $line['product_id'], 'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'], 'tax_rate' => $line['tax_rate'] ?? 0,
                    'subtotal' => $line['subtotal'], 'tax_amount' => $line['tax'], 'total' => $line['total'],
                    'inventory_movement_id' => $movement['movement_id'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $entryId = $this->accounting->postPurchase($purchaseId, [
                'date' => $data['document_date'], 'number' => $number,
                'reference' => $data['supplier_document'] ?: $number,
                'net' => (string) $subtotal->minus($discountTotal), 'tax' => (string) $taxTotal,
                'total' => (string) $grandTotal, 'paid' => (string) $paid,
                'treasury_accounting_id' => $treasury?->accounting_account_id,
            ], $userId);
            DB::table('purchasing_documents')->where('id', $purchaseId)->update(['journal_entry_id' => $entryId]);

            if ($paid->isGreaterThan('0')) {
                DB::table('purchasing_payments')->insert([
                    'purchase_id' => $purchaseId, 'payment_date' => $data['document_date'], 'amount' => (string) $paid,
                    'treasury_account_id' => $treasury->id, 'method' => $data['payment_method'] ?? 'cash',
                    'reference' => $data['supplier_document'] ?: null, 'notes' => 'Pago registrado con la compra',
                    'paid_by' => $userId, 'journal_entry_id' => $entryId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            return ['purchase_id' => (int) $purchaseId, 'number' => $number, 'repeated' => false];
        }, 5);
    }

    public function registerPayment(int $purchaseId, array $data, int $userId): int
    {
        return DB::transaction(function () use ($purchaseId, $data, $userId): int {
            $purchase = DB::table('purchasing_documents')->where('id', $purchaseId)->lockForUpdate()->first();
            $treasury = DB::table('accounting_treasury_accounts')->where('id', (int) $data['treasury_account_id'])->where('active', true)->first();
            if ($purchase === null || $purchase->status !== 'posted' || $treasury === null) {
                throw new DomainException('La compra o la cuenta de pago no está disponible.');
            }
            $amount = BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::HALF_UP);
            $balance = BigDecimal::of((string) $purchase->total)->minus((string) $purchase->paid_amount);
            if (! $amount->isGreaterThan('0') || $amount->isGreaterThan($balance)) {
                throw new DomainException('El pago supera el saldo pendiente o no es válido.');
            }
            $now = now();
            $paymentId = DB::table('purchasing_payments')->insertGetId([
                'purchase_id' => $purchaseId, 'payment_date' => $data['payment_date'], 'amount' => (string) $amount,
                'treasury_account_id' => $treasury->id, 'method' => $data['method'],
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'paid_by' => $userId,
                'journal_entry_id' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $entryId = $this->accounting->postSupplierPayment($paymentId, [
                'date' => $data['payment_date'], 'amount' => (string) $amount,
                'treasury_accounting_id' => $treasury->accounting_account_id, 'reference' => $data['reference'] ?? null,
            ], $userId);
            DB::table('purchasing_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId]);
            DB::table('purchasing_documents')->where('id', $purchaseId)->update([
                'paid_amount' => (string) BigDecimal::of((string) $purchase->paid_amount)->plus($amount), 'updated_at' => $now,
            ]);
            return (int) $paymentId;
        }, 5);
    }
}
