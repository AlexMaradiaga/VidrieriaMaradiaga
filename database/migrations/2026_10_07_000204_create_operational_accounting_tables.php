<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_treasury_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 20)->default('cash');
            $table->foreignId('accounting_account_id')->unique()->constrained('accounting_accounts');
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->foreignId('treasury_account_id')->nullable()->after('payment_type')->constrained('accounting_treasury_accounts');
        });

        Schema::table('sales_payments', function (Blueprint $table): void {
            $table->foreignId('treasury_account_id')->nullable()->after('method')->constrained('accounting_treasury_accounts');
        });

        Schema::create('purchasing_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_key')->unique();
            $table->string('number', 30)->unique();
            $table->string('supplier_document', 100)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->foreignId('supplier_id')->constrained('inventory_suppliers');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->string('status', 15)->default('posted');
            $table->string('payment_type', 10)->default('credit');
            $table->foreignId('treasury_account_id')->nullable()->constrained('accounting_treasury_accounts');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['document_date', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('purchasing_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchasing_documents');
            $table->unsignedInteger('line_number');
            $table->foreignId('product_id')->constrained('inventory_products');
            $table->foreignId('unit_id')->constrained('inventory_units');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
            $table->timestamps();
            $table->unique(['purchase_id', 'line_number']);
        });

        Schema::create('purchasing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchasing_documents');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('treasury_account_id')->constrained('accounting_treasury_accounts');
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->timestamps();
        });

        Schema::create('accounting_expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_key')->unique();
            $table->string('number', 30)->unique();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->string('payee', 150);
            $table->string('category', 30);
            $table->foreignId('expense_account_id')->constrained('accounting_accounts');
            $table->string('document_number', 100)->nullable();
            $table->string('payment_type', 10)->default('cash');
            $table->foreignId('treasury_account_id')->nullable()->constrained('accounting_treasury_accounts');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('notes', 1000)->nullable();
            $table->string('status', 15)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['expense_date', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('accounting_expense_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained('accounting_expenses');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('treasury_account_id')->constrained('accounting_treasury_accounts');
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->timestamps();
        });

        Schema::create('accounting_loans', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('creditor', 150);
            $table->string('description', 250);
            $table->string('reference', 100)->nullable();
            $table->date('start_date');
            $table->decimal('principal', 18, 2);
            $table->decimal('outstanding_principal', 18, 2);
            $table->decimal('annual_interest_rate', 7, 4)->default(0);
            $table->unsignedInteger('installments')->default(1);
            $table->foreignId('liability_account_id')->constrained('accounting_accounts');
            $table->foreignId('interest_expense_account_id')->constrained('accounting_accounts');
            $table->foreignId('treasury_account_id')->constrained('accounting_treasury_accounts');
            $table->string('status', 15)->default('active');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('accounting_loan_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('accounting_loans');
            $table->date('payment_date');
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('late_fee', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->foreignId('treasury_account_id')->constrained('accounting_treasury_accounts');
            $table->string('reference', 100)->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_loan_payments');
        Schema::dropIfExists('accounting_loans');
        Schema::dropIfExists('accounting_expense_payments');
        Schema::dropIfExists('accounting_expenses');
        Schema::dropIfExists('purchasing_payments');
        Schema::dropIfExists('purchasing_lines');
        Schema::dropIfExists('purchasing_documents');
        Schema::table('sales_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('treasury_account_id'));
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('treasury_account_id'));
        Schema::dropIfExists('accounting_treasury_accounts');
    }
};
