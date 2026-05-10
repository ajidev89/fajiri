<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LocationService;
use Illuminate\Support\Facades\Auth;

class CurrencyMiddleware
{
    public function __construct(protected LocationService $locationService) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $currency = 'USD';

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->country && $user->country->currency) {
                $currency = $user->country->currency;
            } else {
                // Fallback to logic: Nigeria = NGN, Canada = CAD, Else = USD
                $countryCode = $user->country->iso2 ?? null;
                $currency = $this->locationService->getCurrencyByCountryCode($countryCode);
            }
        } else {
            // Guest: Detect by IP
            $ip = $request->ip();
            $countryCode = $this->locationService->getCountryCode($ip);
            $currency = $this->locationService->getCurrencyByCountryCode($countryCode);
        }

        // Store detected currency in the request for easy access
        $request->merge(['detected_currency' => $currency]);
        
        // Also share with views if needed (though this is mostly API)
        // view()->share('detected_currency', $currency);

        return $next($request);
    }
}
