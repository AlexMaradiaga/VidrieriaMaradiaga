<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('legal_name', 150)->nullable();
            $table->string('tax_id', 30)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_key')->unique();
            $table->string('number', 30)->unique();
            $table->date('document_date');
            $table->foreignId('customer_id')->constrained('sales_customers');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->string('status', 15)->default('posted');
            $table->string('payment_type', 10)->default('cash');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['document_date', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('sales_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales_documents');
            $table->unsignedInteger('line_number');
            $table->foreignId('product_id')->constrained('inventory_products');
            $table->foreignId('unit_id')->constrained('inventory_units');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
            $table->timestamps();
            $table->unique(['sale_id', 'line_number']);
        });

        Schema::create('sales_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales_documents');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('sales_lines');
        Schema::dropIfExists('sales_documents');
        Schema::dropIfExists('sales_customers');
    }
};
