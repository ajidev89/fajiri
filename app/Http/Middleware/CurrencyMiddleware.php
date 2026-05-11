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
        // Ignore currency detection for admins
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            if ($user->role && in_array($user->role->slug, ['admin', 'super-admin'])) {
                return $next($request);
            }
        }

        $currency = null;

        // 1. Check for explicit override (Query param or Header)
        if ($request->has('currency')) {
            $currency = strtoupper($request->query('currency'));
        } elseif ($request->hasHeader('X-Currency')) {
            $currency = strtoupper($request->header('X-Currency'));
        }

        // 2. If no override, try Authenticated User
        if (!$currency && Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            if ($user->country && $user->country->currency) {
                $currency = $user->country->currency;
            } else {
                $countryCode = $user->country->iso2 ?? null;
                $currency = $this->locationService->getCurrencyByCountryCode($countryCode);
            }
        }

        // 3. Finally, Fallback to IP detection
        if (!$currency) {
            $ip = $request->ip();
            $countryCode = $this->locationService->getCountryCode($ip);
            $currency = $this->locationService->getCurrencyByCountryCode($countryCode);
        }

        // Store detected currency in the request
        $request->merge(['detected_currency' => $currency]);
        
        return $next($request);
    }
}
