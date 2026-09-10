<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('unit_id');
            $table->decimal('conversion_factor_to_base', 18, 8)->default(1);
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_sale_unit')->default(false);
            $table->boolean('is_issue_unit')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('product_id', 'fk_inv_prod_units_product')
                ->references('id')->on('inventory_products');
            $table->foreign('unit_id', 'fk_inv_prod_units_unit')
                ->references('id')->on('inventory_units');
            $table->unique(['product_id', 'unit_id'], 'uq_inv_prod_units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_units');
    }
};
