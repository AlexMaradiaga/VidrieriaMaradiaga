<?php

use App\Modules\Inventory\UI\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function (): void {
        Route::post('/products', [ProductController::class, 'store'])
            ->name('products.store');
    });
