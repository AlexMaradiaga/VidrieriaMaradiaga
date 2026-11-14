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
        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->foreignId('default_warehouse_id')->nullable();
            $table->foreign('default_warehouse_id', 'fk_inv_categories_default_wh')
                ->references('id')->on('inventory_warehouses');
        });

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->string('responsible_name', 180)->nullable();
        });

        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->string('responsible_name', 180)->nullable();
        });

        Schema::create('inventory_tools', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('condition', 20)->default('good');
            $table->string('status', 20)->default('available');
            $table->foreignId('location_id')->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('location_id', 'fk_inv_tools_location')
                ->references('id')->on('inventory_locations');
            $table->index(['active', 'status'], 'ix_inv_tools_status');
        });

        Schema::create('inventory_tool_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_id');
            $table->foreignId('employee_id');
            $table->date('assigned_at');
            $table->date('expected_return_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->string('condition_out', 20)->default('good');
            $table->string('condition_return', 20)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('assigned_by');
            $table->foreignId('returned_by')->nullable();
            $table->timestamps();

            $table->foreign('tool_id', 'fk_inv_tool_assignments_tool')
                ->references('id')->on('inventory_tools');
            $table->foreign('employee_id', 'fk_inv_tool_assignments_employee')
                ->references('id')->on('employees');
            $table->foreign('assigned_by', 'fk_inv_tool_assignments_assigned_by')
                ->references('id')->on('users');
            $table->foreign('returned_by', 'fk_inv_tool_assignments_returned_by')
                ->references('id')->on('users');
            $table->index(['tool_id', 'returned_at'], 'ix_inv_tool_assignments_active');
            $table->index(['employee_id', 'returned_at'], 'ix_inv_tool_assignments_employee');
        });

        $this->configureCategoryWarehouses();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_tool_assignments');
        Schema::dropIfExists('inventory_tools');

        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->dropColumn('responsible_name');
        });

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropColumn('responsible_name');
        });

        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->dropForeign('fk_inv_categories_default_wh');
            $table->dropColumn('default_warehouse_id');
        });
    }

    private function configureCategoryWarehouses(): void
    {
        $now = now();
        $warehouses = [
            'BOD-ADH-BAR' => 'Adhesivos, herrajes y selladores',
            'BOD-VID-ESP' => 'Vidrios y espejos',
            'BOD-PERF-PVC' => 'Perfilería PVC',
            'BOD-PERF-ALU' => 'Perfilería de aluminio',
        ];

        foreach ($warehouses as $code => $name) {
            DB::table('inventory_warehouses')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_default' => false,
                    'active' => true,
                    'deleted_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $warehouseId = DB::table('inventory_warehouses')
                ->where('code', $code)
                ->value('id');

            DB::table('inventory_locations')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'code' => 'GENERAL'],
                [
                    'name' => 'Almacenamiento general',
                    'active' => true,
                    'deleted_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $mapping = [
            'ACCESSORIES' => 'BOD-ADH-BAR',
            'HARDWARE' => 'BOD-ADH-BAR',
            'SEALANTS' => 'BOD-ADH-BAR',
            'GLASS' => 'BOD-VID-ESP',
            'PVC' => 'BOD-PERF-PVC',
            'ALUMINUM' => 'BOD-PERF-ALU',
        ];

        foreach ($mapping as $categoryCode => $warehouseCode) {
            $warehouseId = DB::table('inventory_warehouses')
                ->where('code', $warehouseCode)
                ->value('id');

            DB::table('inventory_categories')
                ->where('code', $categoryCode)
                ->update([
                    'default_warehouse_id' => $warehouseId,
                    'updated_at' => $now,
                ]);
        }
    }
};
