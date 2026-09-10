<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_category_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('unit_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('data_type', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id', 'fk_inv_cat_attr_category')
                ->references('id')->on('inventory_categories');
            $table->foreign('unit_id', 'fk_inv_cat_attr_unit')
                ->references('id')->on('inventory_units');
            $table->unique(['category_id', 'code'], 'uq_inv_cat_attr_code');
            $table->index(['category_id', 'active'], 'ix_inv_cat_attr_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_category_attributes');
    }
};
