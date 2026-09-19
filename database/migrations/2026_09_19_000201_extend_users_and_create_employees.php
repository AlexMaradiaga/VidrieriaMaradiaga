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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('active');
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code', 30)->unique();
            $table->string('national_id', 30)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('department', 80)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->decimal('monthly_salary', 18, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('national_id');
        });

        DB::statement('CREATE UNIQUE INDEX [uq_employees_national_id] ON [employees] ([national_id]) WHERE [national_id] IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX [uq_employees_user_id] ON [employees] ([user_id]) WHERE [user_id] IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['active', 'last_login_at']);
        });
    }
};
