<?php

use App\Modules\Access\UI\Http\Controllers\AccessManagementController;
use App\Modules\Accounting\UI\Http\Controllers\AccountingController;
use App\Modules\Accounting\UI\Http\Controllers\OperationalAccountingController;
use App\Modules\Partners\UI\Http\Controllers\SupplierController;
use App\Modules\Sales\UI\Http\Controllers\CustomerController;
use App\Modules\Sales\UI\Http\Controllers\SalesController;
use App\Modules\Purchasing\UI\Http\Controllers\PurchaseController;
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

    Route::prefix('purchases')->name('purchases.')->group(function (): void {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::post('/{purchaseId}/payments', [PurchaseController::class, 'payment'])->whereNumber('purchaseId')->name('payments.store');
    });

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
        Route::get('/treasury', [OperationalAccountingController::class, 'treasury'])->name('treasury.index');
        Route::post('/treasury', [OperationalAccountingController::class, 'storeTreasury'])->name('treasury.store');
        Route::get('/expenses', [OperationalAccountingController::class, 'expenses'])->name('expenses.index');
        Route::post('/expenses', [OperationalAccountingController::class, 'storeExpense'])->name('expenses.store');
        Route::post('/expenses/{expenseId}/payments', [OperationalAccountingController::class, 'payExpense'])->whereNumber('expenseId')->name('expenses.payments.store');
        Route::get('/loans', [OperationalAccountingController::class, 'loans'])->name('loans.index');
        Route::post('/loans', [OperationalAccountingController::class, 'storeLoan'])->name('loans.store');
        Route::post('/loans/{loanId}/payments', [OperationalAccountingController::class, 'payLoan'])->whereNumber('loanId')->name('loans.payments.store');
    });
});
