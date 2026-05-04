<?php

use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CampaignController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\DonationController;
use App\Http\Controllers\API\InitiativeController;
use App\Http\Controllers\API\NeedController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\OtpController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\InsuranceController;
use App\Http\Controllers\API\UsersController;
use App\Http\Controllers\API\WithdrawalController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\PartnerController;
use App\Http\Controllers\API\DisbursementController;
use App\Http\Controllers\API\FundraiserController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\FamilyMemberController;
use App\Http\Controllers\API\RevenueCatWebhookController;
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

Route::controller(CountryController::class)->group(function () { 
    Route::group(['prefix' => 'countries'], function () {
        Route::get('/', 'index');
    });
});

Route::controller(UserController::class)->middleware(['auth:sanctum'])->group(function () { 
    Route::group(['prefix' => 'user'], function () {
        Route::get('/', 'index');
        Route::post('/change-password', 'changePassword');
        Route::post('/avatar', 'updateAvatar');
        Route::get('/transactions', 'transactions');
        Route::get('/preferences', [\App\Http\Controllers\API\PreferenceController::class, 'index']);
        Route::put('/preferences', [\App\Http\Controllers\API\PreferenceController::class, 'update']);
        Route::post('/pin', 'updatePin');
        Route::put('/profile', 'updateProfile');
        Route::post('transfer', 'transfer');
        Route::get('withdraw-account', 'withdrawAccount');
        Route::get('referrals', 'referrals');
    });
});

Route::controller(CampaignController::class)->group(function () { 
    Route::group(['prefix' => 'campaigns'], function () {
        Route::get('/', 'index');
        Route::get('/urgent', 'urgentCampaigns');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'admin']);
        Route::get('/types', 'types');
        Route::get('/analytics', 'analytics')->middleware(['auth:sanctum', 'admin']);
        Route::get('/user-donated', 'userDonatedCampaigns');
        Route::get('/{campaign}', 'show');
        Route::put('/{campaign}', 'update')->middleware(['auth:sanctum', 'admin']);
        Route::delete('/{campaign}', 'destroy')->middleware(['auth:sanctum', 'admin']);
    });
});

Route::controller(NeedController::class)->group(function () { 
    Route::group(['prefix' => 'needs'], function () {
        Route::get('/', 'index');
        Route::post('/', 'create')->middleware(['auth:sanctum']);
        Route::get('/{need}', 'find');
        Route::put('/{need}', 'update')->middleware(['auth:sanctum']);
        Route::delete('/{need}', 'delete')->middleware(['auth:sanctum']);
    });
});


Route::controller(InitiativeController::class)->group(function () { 
    Route::group(['prefix' => 'initiatives'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'admin']);
        Route::get('/{initiative}', 'show');
        Route::put('/{initiative}', 'update')->middleware(['auth:sanctum', 'admin']);
        Route::delete('/{initiative}', 'destroy')->middleware(['auth:sanctum', 'admin']);
    });
});

Route::controller(UsersController::class)->group(function () { 
    Route::get('/users/account-types', 'account_types');
    Route::group(['prefix' => 'users', 'middleware' => ['auth:sanctum', 'super-admin']], function () {
        Route::get('/', 'index');
        Route::get('/{user}', 'show');
        Route::get('/{user}/audits', 'audits');
        Route::get('/{user}/transactions', 'transactions');
        Route::put('/{user}', 'update');
        Route::put('/{user}/suspend', 'suspend');
        Route::put('/{user}/unsuspend', 'unsuspend');
        Route::delete('/{user}', 'destroy');
    });
});

Route::controller(InsuranceController::class)->group(function () { 
    Route::group(['prefix' => 'insurances'], function () {
        Route::get('/', 'index')->middleware(['auth:sanctum']);
        Route::get('/all', 'all');
        Route::post('/', 'create')->middleware(['auth:sanctum']);
        Route::get('/{insurance}', 'show');
        Route::put('/{insurance}', 'update')->middleware(['auth:sanctum']);
        Route::delete('/{insurance}', 'destroy')->middleware(['auth:sanctum']);
    });
});

Route::controller(DonationController::class)->group(function () {
    Route::group(['prefix' => 'donations'], function () {
        Route::get('/', 'index')->middleware(['auth:sanctum', 'admin']);
        Route::post('/{type}/{id}/wallet', 'donateViaWallet')->middleware(['auth:sanctum']);
        Route::post('/{type}/{id}/paystack/initialize', 'initializePaystack');
        Route::get('/verify', 'verifyPaystack');
    });
});

Route::controller(PlanController::class)->middleware(['auth:sanctum'])->group(function () { 
    Route::group(['prefix' => 'plans'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware(['super-admin']);
        Route::post('/subscribe', 'subscribe');
        Route::put('/{id}', 'update')->middleware(['super-admin']);
        Route::delete('/{id}', 'destroy')->middleware(['super-admin']);
    });
});

Route::controller(PaymentController::class)->group(function () {
    Route::post('/payments/webhook', 'webhook');
    Route::post('/revenuecat/webhook', [RevenueCatWebhookController::class, 'handle']);
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

Route::controller(WithdrawalController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'withdrawals'], function () {
        Route::get('/', 'index')->middleware(['super-admin']);
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
        Route::get('/banks', 'banks');
        Route::get('/resolve-bank-account', 'resolveBankAccount');
        Route::post('/withdraw', 'withdraw');
    });
});

Route::controller(MediaController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'media'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
    });
});

Route::controller(AnalyticsController::class)->middleware(['auth:sanctum','admin'])->group(function () {
    Route::group(['prefix' => 'analytics'], function () {
        Route::get('/', 'index');
        Route::get('/donation-chartly-annualy', 'donationChartlyAnnualy');
        Route::get('/top-performing-campaigns', 'topPerformingCampaigns');
        Route::get('/leaderboard', 'leaderboard')->withoutMiddleware(['auth:sanctum','admin']);
        Route::get('/disbursements', 'disbursementStats')->middleware(['super-admin']);
    });

    Route::controller(FundraiserController::class)->group(function () {
        Route::group(['prefix' => 'fundraisers'], function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::post('/{user}/reset-password', 'resetPassword');
        });
    });
});

Route::controller(CategoryController::class)->group(function () {
    Route::group(['prefix' => 'categories'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'super-admin']);
        Route::put('/{id}', 'update')->middleware(['auth:sanctum', 'super-admin']);
        Route::delete('/{id}', 'destroy')->middleware(['auth:sanctum', 'super-admin']);
    });
});

Route::controller(PostController::class)->group(function () {
    Route::group(['prefix' => 'posts'], function () {
        Route::get('/', 'index');
        Route::get('/{slug}', 'show');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'super-admin']);
        Route::put('/{id}', 'update')->middleware(['auth:sanctum', 'super-admin']);
        Route::delete('/{id}', 'destroy')->middleware(['auth:sanctum', 'super-admin']);
    });
});

Route::controller(EventController::class)->group(function () {
    Route::group(['prefix' => 'events'], function () {
        Route::get('/', 'index');
        Route::get('/{slug}', 'show');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'admin']);
        Route::put('/{id}', 'update')->middleware(['auth:sanctum', 'admin']);
        Route::delete('/{id}', 'destroy')->middleware(['auth:sanctum', 'admin']);
        Route::post('/{id}/attend', 'attend')->middleware(['auth:sanctum']);
        Route::post('/{id}/attend-external', 'attendExternal');
        Route::post('/{id}/paystack/initialize', 'initializePaystack');
        Route::get('/paystack/verify', 'verifyPaystack');
    });
});

Route::controller(PartnerController::class)->group(function () {
    Route::group(['prefix' => 'partners'], function () {
        Route::get('/', 'index');
        Route::get('/{slug}', 'show');
        Route::post('/', 'store')->middleware(['auth:sanctum', 'super-admin']);
        Route::put('/{id}', 'update')->middleware(['auth:sanctum', 'super-admin']);
        Route::delete('/{id}', 'destroy')->middleware(['auth:sanctum', 'super-admin']);
    });
});

Route::controller(DisbursementController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'disbursements'], function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::post('/{id}/disburse', 'disburse')->middleware(['super-admin']);
        Route::post('/{id}/reject', 'reject')->middleware(['super-admin']);
    });
});

Route::controller(FamilyMemberController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::group(['prefix' => 'family-tree'], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
});