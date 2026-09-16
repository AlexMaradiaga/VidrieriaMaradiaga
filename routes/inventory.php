<?php

use App\Modules\Inventory\UI\Http\Controllers\InventoryEntryController;
use App\Modules\Inventory\UI\Http\Controllers\InventoryHistoryController;
use App\Modules\Inventory\UI\Http\Controllers\ProductController;
use App\Modules\Inventory\UI\Http\Controllers\InventoryOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::post('/products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::put('/products/{productId}', [ProductController::class, 'update'])
            ->whereNumber('productId')
            ->name('products.update');

        Route::patch(
            '/products/{productId}/active',
            [ProductController::class, 'setActive']
        )
            ->whereNumber('productId')
            ->name('products.active');

        Route::get('/entries', [InventoryHistoryController::class, 'index'])
            ->name('entries.index');

        Route::get('/entries/create', [InventoryEntryController::class, 'create'])
            ->name('entries.create');

        Route::post('/entries', [InventoryEntryController::class, 'store'])
            ->name('entries.store');

        Route::get(
            '/entries/{movementId}',
            [InventoryHistoryController::class, 'show']
        )
            ->whereNumber('movementId')
            ->name('entries.show');

        Route::get('/kardex', [InventoryHistoryController::class, 'kardex'])
            ->name('kardex.index');

        Route::get('/exits/create', [InventoryOperationsController::class, 'exitsCreate'])->name('exits.create');
        Route::post('/exits', [InventoryOperationsController::class, 'exitsStore'])->name('exits.store');
        Route::get('/transfers/create', [InventoryOperationsController::class, 'transfersCreate'])->name('transfers.create');
        Route::post('/transfers', [InventoryOperationsController::class, 'transfersStore'])->name('transfers.store');

        Route::get('/counts', [InventoryOperationsController::class, 'counts'])->name('counts.index');
        Route::post('/counts', [InventoryOperationsController::class, 'countsStore'])->name('counts.store');
        Route::get('/alerts', [InventoryOperationsController::class, 'alerts'])->name('alerts.index');

        Route::get('/remnants', [InventoryOperationsController::class, 'remnants'])->name('remnants.index');
        Route::post('/remnants', [InventoryOperationsController::class, 'remnantsStore'])->name('remnants.store');
        Route::patch('/remnants/{remnantId}/status', [InventoryOperationsController::class, 'remnantsStatus'])
            ->whereNumber('remnantId')->name('remnants.status');

        Route::get('/kits', [InventoryOperationsController::class, 'kits'])->name('kits.index');
        Route::post('/kits', [InventoryOperationsController::class, 'kitsStore'])->name('kits.store');
        Route::patch('/kits/{kitId}/active', [InventoryOperationsController::class, 'kitsActive'])
            ->whereNumber('kitId')->name('kits.active');

        Route::get('/catalogs', [InventoryOperationsController::class, 'catalogs'])->name('catalogs.index');
        Route::post('/catalogs/{catalog}', [InventoryOperationsController::class, 'catalogStore'])->name('catalogs.store');
        Route::patch('/catalogs/{catalog}/{recordId}/active', [InventoryOperationsController::class, 'catalogActive'])
            ->whereNumber('recordId')->name('catalogs.active');

        Route::get('/cut-calculator', [InventoryOperationsController::class, 'cutCalculator'])->name('cuts.index');
    });
