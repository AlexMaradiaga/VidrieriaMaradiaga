<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts');
            $table->boolean('accepts_entries')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 10)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('entry_date');
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->string('source_type', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 250);
            $table->string('reference', 100)->nullable();
            $table->string('status', 10)->default('posted');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['entry_date', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('accounting_journal_entries');
            $table->unsignedInteger('line_number');
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->string('description', 250)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['entry_id', 'line_number']);
            $table->index(['account_id', 'entry_id']);
        });

        Schema::create('accounting_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_accounts');
    }
};
