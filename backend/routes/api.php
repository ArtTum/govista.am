<?php

use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PackageOfferController;
use App\Http\Controllers\Api\Admin\ProviderController;
use App\Http\Controllers\Api\Admin\ProviderIntegrationController;
use App\Http\Controllers\Api\Admin\TravelRequestController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PackageSearchController;
use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\TravelLocationController;
use App\Http\Controllers\Api\TravelSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/packages/options', [PackageController::class, 'options']);
    Route::get('/packages/offers', [PackageController::class, 'index']);
    Route::get('/packages/offers/{id}', [PackageController::class, 'show'])->whereNumber('id');
    Route::post('/packages/search', [PackageSearchController::class, 'start'])->middleware('throttle:6,1');
    Route::get('/packages/search/{token}', [PackageSearchController::class, 'results'])->middleware('throttle:30,1');
    Route::get('/travel/services', [TravelSearchController::class, 'services']);
    Route::get('/travel/locations', TravelLocationController::class)->middleware('throttle:20,1');
    Route::post('/travel/search', [TravelSearchController::class, 'search'])->middleware('throttle:12,1');
    Route::post('/account/register', [CustomerController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/account/login', [CustomerController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/account/forgot-password', [CustomerController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/account/reset-password', [CustomerController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::get('/me', [CustomerController::class, 'me']);
        Route::post('/logout', [CustomerController::class, 'logout']);
        Route::get('/orders', [CustomerController::class, 'orders']);
        Route::post('/orders/{id}/action', [CustomerController::class, 'action'])->middleware('throttle:20,1');
    });
    Route::get('/settings', [PublicContentController::class, 'siteSettings']);
    Route::get('/pages', [PublicContentController::class, 'pages']);
    Route::get('/home', [PublicContentController::class, 'home']);
    Route::get('/tours', [PublicContentController::class, 'tours']);
    Route::get('/tours/{slug}', [PublicContentController::class, 'tour']);
    Route::get('/destinations', [PublicContentController::class, 'destinations']);
    Route::get('/destinations/{slug}', [PublicContentController::class, 'destination']);
    Route::get('/services', [PublicContentController::class, 'services']);
    Route::get('/services/{slug}', [PublicContentController::class, 'service']);
    Route::get('/posts', [PublicContentController::class, 'posts']);
    Route::get('/posts/{slug}', [PublicContentController::class, 'post']);
    Route::get('/pages/{slug}', [PublicContentController::class, 'page']);
    Route::post('/bookings', [LeadController::class, 'booking'])->middleware('throttle:20,1');
    Route::post('/contact', [LeadController::class, 'contact'])->middleware('throttle:20,1');
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/providers', [ProviderController::class, 'index']);
        Route::put('/providers/{code}/integration/{environment}', [ProviderIntegrationController::class, 'save']);
        Route::post('/providers/{code}/integration/{environment}/inspect', [ProviderIntegrationController::class, 'inspect']);
        Route::post('/providers/{code}/integration/{environment}/test', [ProviderIntegrationController::class, 'test'])->middleware('throttle:5,1');
        Route::post('/providers/{code}/integration/{environment}/dictionary/{kind}', [ProviderIntegrationController::class, 'dictionary'])->middleware('throttle:20,1');
        Route::post('/providers/{code}/integration/{environment}/search', [ProviderIntegrationController::class, 'search'])->middleware('throttle:5,1');
        Route::get('/providers/{code}/dictionary/{kind}', [ProviderController::class, 'dictionary'])->middleware('throttle:10,1');
        Route::put('/providers/{code}', [ProviderController::class, 'update']);
        Route::post('/providers/{code}/test', [ProviderController::class, 'test'])->middleware('throttle:5,1');
        Route::get('/travel/requests', [TravelRequestController::class, 'index']);
        Route::get('/travel/requests/{id}', [TravelRequestController::class, 'show']);
        Route::put('/travel/requests/{id}', [TravelRequestController::class, 'update']);
        Route::get('/travel/reports', [TravelRequestController::class, 'reports']);
        Route::get('/package-offers', [PackageOfferController::class, 'index']);
        Route::post('/package-offers', [PackageOfferController::class, 'store']);
        Route::get('/package-offers/template', [PackageOfferController::class, 'template']);
        Route::post('/package-offers/import/preview', [PackageOfferController::class, 'preview'])->middleware('throttle:10,1');
        Route::post('/package-offers/import/commit', [PackageOfferController::class, 'commit'])->middleware('throttle:10,1');
        Route::get('/package-offers/{id}', [PackageOfferController::class, 'show'])->whereNumber('id');
        Route::put('/package-offers/{id}', [PackageOfferController::class, 'update'])->whereNumber('id');
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', DashboardController::class);
        Route::post('/upload', UploadController::class);
        Route::get('/content/{resource}', [ContentController::class, 'index']);
        Route::post('/content/{resource}', [ContentController::class, 'store']);
        Route::get('/content/{resource}/{id}', [ContentController::class, 'show']);
        Route::put('/content/{resource}/{id}', [ContentController::class, 'update']);
        Route::delete('/content/{resource}/{id}', [ContentController::class, 'destroy']);
    });
});
