<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\KycController;
use App\Http\Controllers\API\OtpController;
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
    });
});
