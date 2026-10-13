<?php

namespace App\Providers;

use App\Enums\User\Status;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Require an authorized user in every environment, including local.
     */
    protected function authorization()
    {
        $this->gate();

        Telescope::auth(function ($request) {
            $user = $request->user();

            return $user instanceof User && $user->can('viewTelescope');
        });
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            $user->loadMissing('role');

            return $user->status === Status::ACTIVE->value
                && in_array($user->role?->slug, ['super-admin', 'admin'], true);
        });
    }
}
