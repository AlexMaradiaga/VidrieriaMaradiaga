<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('product_id');
            $table->foreignId('source_location_id');
            $table->foreignId('destination_location_id');
            $table->decimal('quantity', 18, 4);
            $table->date('document_date');
            $table->string('reference', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('outbound_movement_id');
            $table->foreignId('inbound_movement_id');
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('product_id', 'fk_inv_transfers_product')->references('id')->on('inventory_products');
            $table->foreign('source_location_id', 'fk_inv_transfers_source')->references('id')->on('inventory_locations');
            $table->foreign('destination_location_id', 'fk_inv_transfers_destination')->references('id')->on('inventory_locations');
            $table->foreign('outbound_movement_id', 'fk_inv_transfers_out')->references('id')->on('inventory_movements');
            $table->foreign('inbound_movement_id', 'fk_inv_transfers_in')->references('id')->on('inventory_movements');
            $table->foreign('created_by', 'fk_inv_transfers_user')->references('id')->on('users');
        });

        Schema::create('inventory_counts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('location_id');
            $table->date('document_date');
            $table->string('status', 12)->default('posted');
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('location_id', 'fk_inv_counts_location')->references('id')->on('inventory_locations');
            $table->foreign('created_by', 'fk_inv_counts_user')->references('id')->on('users');
            $table->index(['document_date', 'status'], 'ix_inv_counts_listing');
        });

        Schema::create('inventory_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('count_id');
            $table->foreignId('product_id');
            $table->decimal('expected_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4);
            $table->decimal('variance_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->foreignId('movement_id')->nullable();
            $table->timestamps();

            $table->foreign('count_id', 'fk_inv_count_lines_count')->references('id')->on('inventory_counts');
            $table->foreign('product_id', 'fk_inv_count_lines_product')->references('id')->on('inventory_products');
            $table->foreign('movement_id', 'fk_inv_count_lines_movement')->references('id')->on('inventory_movements');
            $table->unique(['count_id', 'product_id'], 'uq_inv_count_lines_product');
        });

        Schema::create('inventory_remnants', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('product_id');
            $table->foreignId('location_id');
            $table->decimal('width_mm', 12, 2);
            $table->decimal('height_mm', 12, 2);
            $table->decimal('thickness_mm', 8, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 15)->default('available');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('product_id', 'fk_inv_remnants_product')->references('id')->on('inventory_products');
            $table->foreign('location_id', 'fk_inv_remnants_location')->references('id')->on('inventory_locations');
            $table->foreign('created_by', 'fk_inv_remnants_user')->references('id')->on('users');
            $table->index(['status', 'product_id'], 'ix_inv_remnants_status');
        });

        Schema::create('inventory_kits', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->string('description', 1000)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('created_by', 'fk_inv_kits_user')->references('id')->on('users');
        });

        Schema::create('inventory_kit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kit_id');
            $table->foreignId('product_id');
            $table->decimal('quantity', 18, 4);
            $table->timestamps();

            $table->foreign('kit_id', 'fk_inv_kit_items_kit')->references('id')->on('inventory_kits')->cascadeOnDelete();
            $table->foreign('product_id', 'fk_inv_kit_items_product')->references('id')->on('inventory_products');
            $table->unique(['kit_id', 'product_id'], 'uq_inv_kit_items_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_kit_items');
        Schema::dropIfExists('inventory_kits');
        Schema::dropIfExists('inventory_remnants');
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_transfers');
    }
};
