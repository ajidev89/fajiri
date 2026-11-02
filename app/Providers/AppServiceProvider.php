<?php

namespace App\Providers;

use App\Http\Observers\CampaignObserver;
use App\Models\Campaign;
use App\Models\Profile;
use App\Models\User;
use App\Observers\ProfileObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        Campaign::observe(CampaignObserver::class);
        User::observe(UserObserver::class);
        Profile::observe(ProfileObserver::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = strtolower((string) (
                $request->input('email')
                ?? $request->input('phone')
                ?? $request->input('login')
                ?? $request->input('identifier')
                ?? ''
            ));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });
    }
}
