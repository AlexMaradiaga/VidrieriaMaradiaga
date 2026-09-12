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
        Schema::create('inventory_movement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movement_id');
            $table->unsignedInteger('line_number');

            $table->foreignId('product_id');
            $table->foreignId('location_id');

            // Unidad utilizada para registrar la operación.
            $table->foreignId('unit_id');

            // Unidad de control vigente al registrar el movimiento.
            $table->foreignId('base_unit_id');

            // Cantidad ingresada por el usuario.
            $table->decimal('quantity', 18, 4);

            // Copia histórica del factor aplicado.
            $table->decimal('conversion_factor', 18, 8);

            // Cantidad convertida a la unidad base.
            $table->decimal('base_quantity', 18, 4);

            // Costo por unidad utilizada en el documento.
            $table->decimal('unit_cost', 18, 4);

            // Costo convertido por unidad base.
            $table->decimal('base_unit_cost', 18, 8);

            // Importe de la línea.
            $table->decimal('total_cost', 28, 4);

            // Se completan cuando el movimiento queda confirmado.
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->decimal('average_cost_after', 18, 4)->nullable();

            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->foreign('movement_id', 'fk_inv_lines_movement')
                ->references('id')
                ->on('inventory_movements');

            $table->foreign('product_id', 'fk_inv_lines_product')
                ->references('id')
                ->on('inventory_products');

            $table->foreign('location_id', 'fk_inv_lines_location')
                ->references('id')
                ->on('inventory_locations');

            $table->foreign('unit_id', 'fk_inv_lines_unit')
                ->references('id')
                ->on('inventory_units');

            $table->foreign('base_unit_id', 'fk_inv_lines_base_unit')
                ->references('id')
                ->on('inventory_units');

            $table->unique(
                ['movement_id', 'line_number'],
                'uq_inv_lines_number'
            );

            $table->index(
                ['product_id', 'movement_id'],
                'ix_inv_lines_product_movement'
            );

            $table->index(
                ['product_id', 'location_id'],
                'ix_inv_lines_product_location'
            );
        });

        DB::statement("
            ALTER TABLE [inventory_movement_lines]
            ADD CONSTRAINT [ck_inv_lines_positive_quantity]
            CHECK (
                [line_number] > 0
                AND [quantity] > 0
                AND [conversion_factor] > 0
                AND [base_quantity] > 0
            )
        ");

        DB::statement("
            ALTER TABLE [inventory_movement_lines]
            ADD CONSTRAINT [ck_inv_lines_nonnegative_cost]
            CHECK (
                [unit_cost] >= 0
                AND [base_unit_cost] >= 0
                AND [total_cost] >= 0
                AND (
                    [average_cost_after] IS NULL
                    OR [average_cost_after] >= 0
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movement_lines');
    }
};
