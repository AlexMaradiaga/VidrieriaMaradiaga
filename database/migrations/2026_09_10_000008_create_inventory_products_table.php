<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('base_unit_id');
            $table->string('sku', 50)->unique();
            $table->string('barcode', 80)->nullable();
            $table->string('name', 180);
            $table->string('description', 1000)->nullable();
            $table->string('product_type', 30)->default('material');
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('maximum_stock', 18, 4)->nullable();
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->decimal('last_purchase_cost', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 4)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('track_lots')->default(false);
            $table->boolean('track_remnants')->default(false);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id', 'fk_inv_products_category')
                ->references('id')->on('inventory_categories');
            $table->foreign('base_unit_id', 'fk_inv_products_base_unit')
                ->references('id')->on('inventory_units');
            $table->index('barcode', 'ix_inv_products_barcode');
            $table->index(['category_id', 'active'], 'ix_inv_products_category_active');
            $table->index(['active', 'name'], 'ix_inv_products_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
