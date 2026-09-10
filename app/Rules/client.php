<?php

use App\Http\Controllers\Client\Auth\AuthenticatedClientSessionController;
use App\Http\Controllers\Client\Auth\RegisteredClientController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Middleware\Client\EnsureClientIsAuthenticated;
use App\Http\Middleware\Client\RedirectIfClientAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('client.')->group(function () {

    Route::middleware(RedirectIfClientAuthenticated::class)->group(function () {
        Route::get('register', [RegisteredClientController::class, 'create'])->name('register');
        Route::post('register', [RegisteredClientController::class, 'store']);

        Route::get('login', [AuthenticatedClientSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedClientSessionController::class, 'store']);
    });

    Route::middleware(EnsureClientIsAuthenticated::class)->group(function () {
        Route::get('dashboard', ClientDashboardController::class)->name('dashboard');
        Route::post('logout', [AuthenticatedClientSessionController::class, 'destroy'])->name('logout');
    });
});
