<?php

use App\Modules\Inventory\UI\Http\Controllers\InventoryDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', InventoryDashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/inventory.php';
