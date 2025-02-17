<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'web'])->group(function () {
    Route::get('/xero/auth/authorize', [XeroController::class, 'authorize']);
    Route::get('/xero/auth/callback', [XeroController::class, 'callback']);
});
