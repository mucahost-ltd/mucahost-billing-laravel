<?php

use App\Http\Controllers\Client\Auth\AuthenticatedClientSessionController;
use App\Http\Controllers\Client\Auth\RegisteredClientController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\TicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Middleware\Client\EnsureClientIsAuthenticated;
use App\Http\Middleware\Client\RedirectIfClientAuthenticated;
use Illuminate\Support\Facades\Route;

Route::name('client.')->group(function () {

    Route::middleware(RedirectIfClientAuthenticated::class)->group(function () {
        Route::get('register', [RegisteredClientController::class, 'create'])->name('register');
        Route::post('register', [RegisteredClientController::class, 'store'])->name('register.store');

        Route::get('login', [AuthenticatedClientSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedClientSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(EnsureClientIsAuthenticated::class)->group(function () {
        Route::get('orders/{order}', [OrderController::class, 'confirmation'])->name('orders.show');
        Route::get('order', [OrderController::class, 'create'])->name('orders.create');
        Route::post('order', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
        Route::get('invoices/{invoice}', [OrderController::class, 'show'])->name('invoices.show');
        Route::get('dashboard', ClientDashboardController::class)->name('dashboard');
        Route::post('logout', [AuthenticatedClientSessionController::class, 'destroy'])->name('logout');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/replies/{reply}/attachments/{attachment}', [TicketAttachmentController::class, 'showForClient'])
            ->scopeBindings()
            ->whereNumber('attachment')
            ->name('tickets.attachments.show');
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    });
});
