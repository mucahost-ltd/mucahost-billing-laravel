<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductGroupController;
use App\Http\Controllers\SupportDepartmentController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/client.php';

Route::prefix('admin')->group(function () {
    Route::get('/', function (Request $request): RedirectResponse {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        abort_if(! $team, 403);

        return redirect()->route('dashboard', ['current_team' => $team->slug]);
    })->middleware('auth:web')->name('admin');

    Route::prefix('{current_team}')
        ->middleware(['auth', 'verified', EnsureTeamMembership::class])
        ->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
        });

    Route::middleware(['auth'])->group(function () {
        Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
        Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
    });

    require __DIR__.'/settings.php';

    Route::middleware('auth:web')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('invoices', [BillingController::class, 'invoices'])->name('invoices.index');
        Route::patch('products/{product}/plan', [BillingController::class, 'updatePlan'])->name('plans.update');
        Route::post('invoices/{invoice}/confirm', [BillingController::class, 'confirm'])->name('confirm');
        Route::post('orders/{order}/accept', [BillingController::class, 'accept'])->name('accept');
        Route::post('services/{service}/activate', [BillingController::class, 'activate'])->name('activate');
        Route::post('services/{service}/retry', [BillingController::class, 'retry'])->name('retry');
    });

    Route::get('clients', [ClientController::class, 'index'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.index');
    Route::get('clients/create', [ClientController::class, 'create'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.create');
    Route::post('clients', [ClientController::class, 'store'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.store');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.edit');
    Route::patch('clients/{client}', [ClientController::class, 'update'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.update');
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.status');
    Route::post('clients/{client}/notes', [ClientController::class, 'storeNote'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.notes.store');
    Route::delete('clients/{client}/notes/{note}', [ClientController::class, 'destroyNote'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.notes.destroy');
    Route::get('clients/{client}', [ClientController::class, 'show'])
        ->middleware(['auth:web', 'can:manage-clients'])
        ->name('clients.show');

    Route::middleware(['auth:web', 'can:manage-products'])->group(function () {
        Route::get('products/enhance-plans', [ProductController::class, 'plans'])->name('products.plans');
        Route::resource('products', ProductController::class)->except('show');
        Route::post('product-groups', [ProductGroupController::class, 'store'])->name('product-groups.store');
        Route::patch('product-groups/{group}', [ProductGroupController::class, 'update'])->name('product-groups.update');
        Route::delete('product-groups/{group}', [ProductGroupController::class, 'destroy'])->name('product-groups.destroy');
    });

    Route::middleware(['auth:web', 'can:manage-products'])->group(function () {
        Route::post('support-departments', [SupportDepartmentController::class, 'store'])->name('support-departments.store');
        Route::patch('support-departments/{department}', [SupportDepartmentController::class, 'update'])->name('support-departments.update');
        Route::delete('support-departments/{department}', [SupportDepartmentController::class, 'destroy'])->name('support-departments.destroy');

        Route::get('support', [AdminTicketController::class, 'index'])->name('support.index');
        Route::get('support/{ticket}', [AdminTicketController::class, 'show'])->name('support.show');
        Route::get('support/{ticket}/replies/{reply}/attachments/{attachment}', [TicketAttachmentController::class, 'showForAdmin'])
            ->scopeBindings()
            ->whereNumber('attachment')
            ->name('support.attachments.show');
        Route::post('support/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('support.reply');
        Route::patch('support/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('support.status');
        Route::patch('support/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('support.assign');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
