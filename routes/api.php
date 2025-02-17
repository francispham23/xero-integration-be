<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'web', \App\Http\Middleware\Cors::class])->group(function () {
    // Auth routes
    Route::get('/xero/auth/authorize', [XeroController::class, 'authorize']);
    Route::get('/xero/auth/callback', [XeroController::class, 'callback']);

    // Data routes
    Route::get('/xero/vendors', [XeroController::class, 'getVendors']);
    Route::get('/xero/accounts', [XeroController::class, 'getAccounts']);

    // Local data routes
    Route::get('/xero/local/accounts', [XeroController::class, 'getLocalAccounts']);
    Route::get('/xero/local/vendors', [XeroController::class, 'getLocalVendors']);

    // Disconnect route
    Route::match(['get', 'post'], '/xero/auth/disconnect', [XeroController::class, 'disconnect']);
});
