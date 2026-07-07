<?php

use App\Http\Controllers\Admin\BusinessOwnerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReferralTokenController;
use App\Http\Controllers\Referral\ReferralLandingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('business-owners', BusinessOwnerController::class)
        ->except(['create', 'edit']);

    Route::prefix('business-owners/{businessOwner}/referral-tokens')
        ->name('business-owners.referral-tokens.')
        ->group(function () {
            Route::post('/', [ReferralTokenController::class, 'store'])->name('store');
            Route::post('/{referralToken}/regenerate', [ReferralTokenController::class, 'regenerate'])->name('regenerate');
            Route::post('/{referralToken}/revoke', [ReferralTokenController::class, 'revoke'])->name('revoke');
            Route::post('/{referralToken}/mark-sent', [ReferralTokenController::class, 'markSent'])->name('mark-sent');
            Route::patch('/{referralToken}/expiry', [ReferralTokenController::class, 'setExpiry'])->name('expiry');
        });
});

Route::middleware(['referral'])->group(function () {
    Route::get('/r/{token}', [ReferralLandingController::class, 'show'])->name('referral.show');
    Route::post('/r/{token}/confirm', [ReferralLandingController::class, 'confirm'])->name('referral.confirm');
});
