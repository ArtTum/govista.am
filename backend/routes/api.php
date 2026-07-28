<?php

use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PublicContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/home', [PublicContentController::class, 'home']);
    Route::get('/tours', [PublicContentController::class, 'tours']);
    Route::get('/tours/{slug}', [PublicContentController::class, 'tour']);
    Route::get('/destinations', [PublicContentController::class, 'destinations']);
    Route::get('/destinations/{slug}', [PublicContentController::class, 'destination']);
    Route::get('/services', [PublicContentController::class, 'services']);
    Route::get('/posts', [PublicContentController::class, 'posts']);
    Route::get('/posts/{slug}', [PublicContentController::class, 'post']);
    Route::get('/pages/{slug}', [PublicContentController::class, 'page']);
    Route::post('/bookings', [LeadController::class, 'booking'])->middleware('throttle:20,1');
    Route::post('/contact', [LeadController::class, 'contact'])->middleware('throttle:20,1');
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
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
