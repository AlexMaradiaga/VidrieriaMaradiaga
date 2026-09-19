<?php

use App\Modules\Access\UI\Http\Controllers\AccessManagementController;
use App\Modules\Accounting\UI\Http\Controllers\AccountingController;
use App\Modules\Partners\UI\Http\Controllers\SupplierController;
use App\Modules\Sales\UI\Http\Controllers\CustomerController;
use App\Modules\Sales\UI\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::prefix('access')->name('access.')->group(function (): void {
        Route::get('/', [AccessManagementController::class, 'index'])->name('index');
        Route::post('/users', [AccessManagementController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{userId}', [AccessManagementController::class, 'updateUser'])->whereNumber('userId')->name('users.update');
        Route::post('/roles', [AccessManagementController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{roleId}', [AccessManagementController::class, 'updateRole'])->whereNumber('roleId')->name('roles.update');
        Route::post('/employees', [AccessManagementController::class, 'storeEmployee'])->name('employees.store');
        Route::patch('/employees/{employeeId}/active', [AccessManagementController::class, 'toggleEmployee'])->whereNumber('employeeId')->name('employees.active');
    });

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('/suppliers/{supplierId}/active', [SupplierController::class, 'active'])->whereNumber('supplierId')->name('suppliers.active');

    Route::prefix('sales')->name('sales.')->group(function (): void {
        Route::get('/', [SalesController::class, 'index'])->name('index');
        Route::get('/create', [SalesController::class, 'create'])->name('create');
        Route::post('/', [SalesController::class, 'store'])->name('store');
        Route::post('/{saleId}/payments', [SalesController::class, 'payment'])->whereNumber('saleId')->name('payments.store');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::patch('/customers/{customerId}/active', [CustomerController::class, 'active'])->whereNumber('customerId')->name('customers.active');
    });

    Route::prefix('accounting')->name('accounting.')->group(function (): void {
        Route::get('/', [AccountingController::class, 'index'])->name('index');
        Route::post('/accounts', [AccountingController::class, 'account'])->name('accounts.store');
        Route::post('/periods', [AccountingController::class, 'period'])->name('periods.store');
        Route::patch('/periods/{periodId}/close', [AccountingController::class, 'closePeriod'])->whereNumber('periodId')->name('periods.close');
        Route::post('/journal', [AccountingController::class, 'journal'])->name('journal.store');
    });
});
