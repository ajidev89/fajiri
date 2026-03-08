<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CampaignController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\DonationController;
use App\Http\Controllers\API\KycController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\OtpController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;


Route::controller(AuthController::class)->group(function () { 
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', 'login');
        Route::post('/register', 'register');
        Route::post('/change-password', 'changePassword');
        Route::post('/generate-token', 'generateToken');
        Route::post('/logout', 'logout')->middleware(['auth:sanctum']);
    });
    Route::group(['prefix' => 'google'], function () {
        Route::post('/login', 'loginWithGoogle');
        Route::post('/generate-url', 'generateGoogleUrl');
        Route::post('/callback', 'handleGoogleCallback');
    });
});

Route::controller(OtpController::class)->group(function () { 
    Route::group(['prefix' => 'otp'], function () {
        Route::post('/send', 'index');
        Route::post('/verify', 'verify');
    });
});

Route::controller(UserController::class)->middleware(['auth:sanctum'])->group(function () { 
    Route::group(['prefix' => 'user'], function () {
        Route::get('/', 'index');
        Route::post('/change-password', 'changePassword');
        Route::post('/avatar', 'updateAvatar');
    });
});

Route::controller(CampaignController::class)->group(function () { 
    Route::group(['prefix' => 'campaigns'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'admin']);
        Route::get('/{campaign}', 'show');
        Route::put('/{campaign}', 'update')->middleware(['auth:sanctum', 'admin']);
        Route::delete('/{campaign}', 'destroy')->middleware(['auth:sanctum', 'admin']);
    });
});

Route::controller(DonationController::class)->group(function () {
    Route::group(['prefix' => 'donations'], function () {
        Route::post('/{campaignId}/wallet', 'donateViaWallet')->middleware(['auth:sanctum']);
        Route::post('/{campaignId}/paystack/initialize', 'initializePaystack');
        Route::get('/verify', 'verifyPaystack');
    });
});

Route::controller(PlanController::class)->middleware(['auth:sanctum'])->group(function () { 
    Route::group(['prefix' => 'plans'], function () {
        Route::get('/', 'index');
        Route::post('/subscribe', 'subscribe');
        Route::put('/{id}', 'update')->middleware(['admin']);
    });
});

Route::controller(PaymentController::class)->group(function () {
    Route::post('/payments/webhook', 'webhook');
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'payments'], function () {
        Route::post('/initialize', 'initialize');
        Route::get('/verify', 'verify');
    });
});

Route::controller(NotificationController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'notifications'], function () {
        Route::get('/', 'index');
        Route::delete('/{id}', 'destroy');
    });
});
