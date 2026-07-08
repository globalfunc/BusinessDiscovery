<?php

use App\Http\Controllers\Admin\BusinessOwnerController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PipelineController;
use App\Http\Controllers\Admin\ReferralTokenController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaxonomyCategoryController;
use App\Http\Controllers\Admin\TaxonomyNicheController;
use App\Http\Controllers\Discovery\BrandingController;
use App\Http\Controllers\Discovery\DiscoveryController;
use App\Http\Controllers\Discovery\IntakeController;
use App\Http\Controllers\Discovery\SelectedServiceController;
use App\Http\Controllers\Discovery\SuggestionController;
use App\Http\Controllers\Discovery\UploadController;
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

    Route::prefix('pipeline')->name('pipeline.')->group(function () {
        Route::get('/', [PipelineController::class, 'index'])->name('index');
        Route::patch('/business-owners/{businessOwner}/stage', [PipelineController::class, 'updateStage'])->name('update-stage');
    });

    Route::prefix('business-owners/{businessOwner}/referral-tokens')
        ->name('business-owners.referral-tokens.')
        ->group(function () {
            Route::post('/', [ReferralTokenController::class, 'store'])->name('store');
            Route::post('/{referralToken}/regenerate', [ReferralTokenController::class, 'regenerate'])->name('regenerate');
            Route::post('/{referralToken}/revoke', [ReferralTokenController::class, 'revoke'])->name('revoke');
            Route::post('/{referralToken}/mark-sent', [ReferralTokenController::class, 'markSent'])->name('mark-sent');
            Route::patch('/{referralToken}/expiry', [ReferralTokenController::class, 'setExpiry'])->name('expiry');
        });

    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('index');

        Route::post('/taxonomy-categories', [TaxonomyCategoryController::class, 'store'])->name('taxonomy-categories.store');
        Route::patch('/taxonomy-categories/{taxonomyCategory}', [TaxonomyCategoryController::class, 'update'])->name('taxonomy-categories.update');

        Route::post('/taxonomy-categories/{taxonomyCategory}/niches', [TaxonomyNicheController::class, 'store'])->name('taxonomy-niches.store');
        Route::patch('/taxonomy-niches/{taxonomyNiche}', [TaxonomyNicheController::class, 'update'])->name('taxonomy-niches.update');

        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::patch('/services/{service}', [ServiceController::class, 'update'])->name('services.update');

        Route::patch('/settings/{key}', [SettingController::class, 'update'])->name('settings.update');
    });
});

Route::middleware(['signed'])->get('/uploads/{upload}/file', [UploadController::class, 'show'])->name('uploads.show');

Route::middleware(['referral'])->group(function () {
    Route::get('/r/{token}', [ReferralLandingController::class, 'show'])->name('referral.show');
    Route::post('/r/{token}/confirm', [ReferralLandingController::class, 'confirm'])->name('referral.confirm');
});

Route::post('/language', [DiscoveryController::class, 'setLanguage'])->name('language.set');

Route::middleware(['discovery.access'])->prefix('discovery')->name('discovery.')->group(function () {
    Route::patch('/answers', [DiscoveryController::class, 'updateAnswer'])->name('answers.update');
    Route::post('/navigate', [DiscoveryController::class, 'navigate'])->name('navigate');
    Route::post('/intake', [IntakeController::class, 'store'])->name('intake.store');
    Route::post('/submit', [DiscoveryController::class, 'submit'])->name('submit');
    Route::post('/suggest/services', [SuggestionController::class, 'services'])->name('suggest.services');
    Route::post('/suggest/branding', [SuggestionController::class, 'branding'])->name('suggest.branding');
    Route::post('/suggest/content-social', [SuggestionController::class, 'contentSocial'])->name('suggest.content_social');
    Route::post('/suggest/growth/{module}', [SuggestionController::class, 'growth'])->name('suggest.growth');
    Route::post('/services', [SelectedServiceController::class, 'store'])->name('services.store');
    Route::patch('/services/{selectedService}', [SelectedServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{selectedService}', [SelectedServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::delete('/uploads/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
    Route::get('/branding/logo-colors', [BrandingController::class, 'logoColors'])->name('branding.logo-colors');
    Route::get('/{phase?}', [DiscoveryController::class, 'show'])->name('show');
});
