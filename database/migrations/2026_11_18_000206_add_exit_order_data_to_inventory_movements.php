<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable();
            $table->string('requested_by', 180)->nullable();
            $table->string('customer_name', 180)->nullable();
            $table->string('customer_address', 500)->nullable();

            $table->foreign('customer_id', 'fk_inv_movements_customer')
                ->references('id')
                ->on('sales_customers');

            $table->index(['direction', 'reference'], 'ix_inv_movements_order_reference');
            $table->index('customer_id', 'ix_inv_movements_customer');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign('fk_inv_movements_customer');
            $table->dropIndex('ix_inv_movements_order_reference');
            $table->dropIndex('ix_inv_movements_customer');
            $table->dropColumn(['customer_id', 'requested_by', 'customer_name', 'customer_address']);
        });
    }
};
