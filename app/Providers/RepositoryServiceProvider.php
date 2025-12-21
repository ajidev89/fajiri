<?php

namespace App\Providers;

use App\Http\Repository\AuthRepository;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Repository\Contracts\CountryRepositoryInterface;
use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Repository\CountryRepository;
use App\Http\Repository\OtpRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public $bindings = [
        CountryRepositoryInterface::class =>  CountryRepository::class,
        AuthRepositoryInterface::class => AuthRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class
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
