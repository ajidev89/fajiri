<?php

namespace App\Providers;

use App\Http\Repository\AuthRepository;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Repository\Contracts\CountryRepositoryInterface;
use App\Http\Repository\Contracts\GoogleRepositoryInterface;
use App\Http\Repository\Contracts\KycRepositoryInterface;
use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Repository\CountryRepository;
use App\Http\Repository\GoogleRepository;
use App\Http\Repository\KycRepository;
use App\Http\Repository\OtpRepository;
use App\Http\Repository\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public $bindings = [
        CountryRepositoryInterface::class =>  CountryRepository::class,
        AuthRepositoryInterface::class => AuthRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        KycRepositoryInterface::class => KycRepository::class,
        GoogleRepositoryInterface::class => GoogleRepository::class,
    ];
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
