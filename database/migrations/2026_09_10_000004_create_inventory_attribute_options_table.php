<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_attribute_id');
            $table->string('value', 100);
            $table->string('label', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('category_attribute_id', 'fk_inv_attr_opt_attribute')
                ->references('id')->on('inventory_category_attributes');
            $table->unique(
                ['category_attribute_id', 'value'],
                'uq_inv_attr_opt_value'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_attribute_options');
    }
};
