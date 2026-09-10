<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('category_attribute_id');
            $table->foreignId('attribute_option_id')->nullable();
            $table->string('value_text', 1000)->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 18, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->timestamps();

            $table->foreign('product_id', 'fk_inv_prod_attr_product')
                ->references('id')->on('inventory_products');
            $table->foreign('category_attribute_id', 'fk_inv_prod_attr_attribute')
                ->references('id')->on('inventory_category_attributes');
            $table->foreign('attribute_option_id', 'fk_inv_prod_attr_option')
                ->references('id')->on('inventory_attribute_options');
            $table->unique(
                ['product_id', 'category_attribute_id'],
                'uq_inv_prod_attr_value'
            );
            $table->index('attribute_option_id', 'ix_inv_prod_attr_option');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_attributes');
    }
};
