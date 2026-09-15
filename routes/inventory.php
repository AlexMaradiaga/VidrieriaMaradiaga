<?php

use App\Modules\Inventory\UI\Http\Controllers\InventoryEntryController;
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

        Route::patch('/products/{productId}/active', [ProductController::class, 'setActive'])
            ->whereNumber('productId')
            ->name('products.active');

        Route::post('/entries', [InventoryEntryController::class, 'store'])
            ->name('entries.store');
            
        Route::get('/entries/create', [InventoryEntryController::class, 'create'])
            ->name('entries.create');

    });
