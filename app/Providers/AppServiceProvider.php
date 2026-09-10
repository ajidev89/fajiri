<?php

namespace App\Providers;

use App\Http\Observers\CampaignObserver;
use App\Models\Campaign;
use App\Models\Profile;
use App\Models\User;
use App\Observers\ProfileObserver;
use App\Observers\UserObserver;
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
        Campaign::observe(CampaignObserver::class);
        User::observe(UserObserver::class);
        Profile::observe(ProfileObserver::class);

    }
}
