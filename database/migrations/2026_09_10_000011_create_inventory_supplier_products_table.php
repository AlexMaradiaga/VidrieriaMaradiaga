<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id');
            $table->foreignId('product_id');
            $table->string('supplier_sku', 80)->nullable();
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->decimal('minimum_order_quantity', 18, 4)->default(0);
            $table->decimal('last_cost', 18, 4)->default(0);
            $table->boolean('is_preferred')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('supplier_id', 'fk_inv_supp_prod_supplier')
                ->references('id')->on('inventory_suppliers');
            $table->foreign('product_id', 'fk_inv_supp_prod_product')
                ->references('id')->on('inventory_products');
            $table->unique(['supplier_id', 'product_id'], 'uq_inv_supp_prod');
            $table->index(['product_id', 'is_preferred'], 'ix_inv_supp_prod_preferred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_supplier_products');
    }
};
