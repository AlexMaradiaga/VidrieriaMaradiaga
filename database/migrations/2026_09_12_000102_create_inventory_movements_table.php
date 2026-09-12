<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();

            // Identifica una solicitud de creación para evitar duplicarla.
            $table->uuid('operation_key')->unique();

            // inbound: entrada; outbound: salida.
            $table->string('direction', 10);

            // Ejemplos: purchase, initial_balance, sale, internal_consumption.
            $table->string('reason', 40);

            // draft, posted, cancelled.
            $table->string('status', 12)->default('draft');

            $table->date('document_date');
            $table->string('reference', 100)->nullable();
            $table->string('notes', 1000)->nullable();

            $table->foreignId('supplier_id')->nullable();

            $table->foreignId('created_by');
            $table->foreignId('posted_by')->nullable();
            $table->dateTime('posted_at')->nullable();

            $table->foreignId('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->timestamps();

            $table->foreign('supplier_id', 'fk_inv_movements_supplier')
                ->references('id')
                ->on('inventory_suppliers');

            $table->foreign('created_by', 'fk_inv_movements_creator')
                ->references('id')
                ->on('users');

            $table->foreign('posted_by', 'fk_inv_movements_poster')
                ->references('id')
                ->on('users');

            $table->foreign('cancelled_by', 'fk_inv_movements_canceller')
                ->references('id')
                ->on('users');

            $table->index(
                ['direction', 'status', 'document_date'],
                'ix_inv_movements_listing'
            );

            $table->index('supplier_id', 'ix_inv_movements_supplier');
        });

        DB::statement("
            ALTER TABLE [inventory_movements]
            ADD CONSTRAINT [ck_inv_movements_direction]
            CHECK ([direction] IN ('inbound', 'outbound'))
        ");

        DB::statement("
            ALTER TABLE [inventory_movements]
            ADD CONSTRAINT [ck_inv_movements_status]
            CHECK ([status] IN ('draft', 'posted', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE [inventory_movements]
            ADD CONSTRAINT [ck_inv_movements_posted]
            CHECK (
                ([status] = 'posted'
                    AND [posted_at] IS NOT NULL
                    AND [posted_by] IS NOT NULL)
                OR
                ([status] <> 'posted'
                    AND [posted_at] IS NULL
                    AND [posted_by] IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE [inventory_movements]
            ADD CONSTRAINT [ck_inv_movements_cancelled]
            CHECK (
                ([status] = 'cancelled'
                    AND [cancelled_at] IS NOT NULL
                    AND [cancelled_by] IS NOT NULL
                    AND [cancellation_reason] IS NOT NULL)
                OR
                ([status] <> 'cancelled'
                    AND [cancelled_at] IS NULL
                    AND [cancelled_by] IS NULL
                    AND [cancellation_reason] IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
