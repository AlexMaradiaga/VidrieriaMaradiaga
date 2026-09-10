<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('zone', 50)->nullable();
            $table->string('aisle', 30)->nullable();
            $table->string('rack', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_id', 'fk_inv_locations_warehouse')
                ->references('id')->on('inventory_warehouses');
            $table->unique(['warehouse_id', 'code'], 'uq_inv_locations_code');
            $table->index(['warehouse_id', 'active'], 'ix_inv_locations_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
