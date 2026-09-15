<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface InventoryDashboardQueryInterface
{
    /**
     * @return array{
     *   inventory_value: string, products_total: int, products_active: int,
     *   pending_entries: int, critical_products: int,
     *   low_stock: list<array{id: int, sku: string, name: string,
     *     unit_symbol: string, quantity: string, minimum_stock: string}>
     * }
     */
    public function get(): array;
}
