<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('symbol', 20);
            $table->string('dimension', 30)->default('quantity');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'name'], 'ix_inv_units_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
