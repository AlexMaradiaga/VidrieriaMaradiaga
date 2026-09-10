<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id', 'fk_inv_categories_parent')
                ->references('id')
                ->on('inventory_categories');
            $table->index(['active', 'sort_order'], 'ix_inv_categories_active_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
