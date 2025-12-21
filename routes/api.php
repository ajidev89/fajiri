<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\OtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::controller(AuthController::class)->group(function () { 
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', 'login');
        Route::post('/register', 'register');
        Route::post('/change-password', 'changePassword');
        Route::post('/logout', 'logout')->middleware(['auth:sanctum']);
    });
});

Route::controller(OtpController::class)->group(function () { 
    Route::group(['prefix' => 'otp'], function () {
        Route::post('/', 'index');
        Route::post('/verify', 'verify');
    });
});

Route::controller(CountryController::class)->group(function () { 
    Route::group(['prefix' => 'country'], function () {
        Route::post('/', 'index');
    });
});