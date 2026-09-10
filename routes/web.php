<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard/Index');
})
    ->middleware('auth')
    ->name('dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/inventory.php';
