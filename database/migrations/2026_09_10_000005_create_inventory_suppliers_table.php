<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('legal_name', 150);
            $table->string('trade_name', 150)->nullable();
            $table->string('tax_id', 30)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tax_id', 'ix_inv_suppliers_tax_id');
            $table->index(['active', 'legal_name'], 'ix_inv_suppliers_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_suppliers');
    }
};
