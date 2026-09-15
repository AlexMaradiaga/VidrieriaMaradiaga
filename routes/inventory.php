<?php

use App\Modules\Inventory\UI\Http\Controllers\InventoryEntryController;
use App\Modules\Inventory\UI\Http\Controllers\InventoryHistoryController;
use App\Modules\Inventory\UI\Http\Controllers\ProductController;
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
    });
