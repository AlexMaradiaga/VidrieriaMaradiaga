<?php

use App\Modules\Business\UI\Http\Controllers\BusinessDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', BusinessDashboardController::class)
    ->middleware(['auth', 'active'])
    ->name('dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/inventory.php';
require __DIR__.'/business.php';
