<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('location_id');

            // Cantidad expresada en la unidad base del producto.
            $table->decimal('quantity', 18, 4)->default(0);

            $table->timestamps();

            $table->foreign('product_id', 'fk_inv_balances_product')
                ->references('id')
                ->on('inventory_products');

            $table->foreign('location_id', 'fk_inv_balances_location')
                ->references('id')
                ->on('inventory_locations');

            $table->unique(
                ['product_id', 'location_id'],
                'uq_inv_balances_product_location'
            );

            $table->index('location_id', 'ix_inv_balances_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_balances');
    }
};
