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
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
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
        $this->configureUploadValidationMessages();

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

    protected function configureUploadValidationMessages(): void
    {
        app('translator')->load('*', 'validation', 'en');

        Lang::addLines([
            'validation.max.file' => 'The :attribute is too large. The maximum size is :max.',
            'validation.uploaded' => 'The :attribute is too large.',
        ], 'en');

        Validator::replacer('max', function (string $message, string $attribute, string $rule, array $parameters): string {
            $max = (string) ($parameters[0] ?? '');

            if (str_contains($message, 'too large') && is_numeric($max)) {
                $kilobytes = (int) $max;
                $max = $kilobytes > 0 && $kilobytes % 1024 === 0
                    ? ($kilobytes / 1024).' MB'
                    : $kilobytes.' KB';
            }

            return str_replace(':max', $max, $message);
        });
    }
}
