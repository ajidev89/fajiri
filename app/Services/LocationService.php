<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocationService
{
    /**
     * Get country code from IP address.
     */
    public function getCountryCode(string $ip): ?string
    {
        // Skip for local IPs
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'NG'; // Default to Nigeria for local dev
        }

        $cacheKey = "location_ip_{$ip}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($ip) {
            try {
                $response = Http::get("http://ip-api.com/json/{$ip}?fields=countryCode");

                if ($response->successful()) {
                    return $response->json('countryCode');
                }
            } catch (\Exception $e) {
                Log::error('Location Service Error', ['ip' => $ip, 'error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Get currency based on country code.
     */
    public function getCurrencyByCountryCode(?string $countryCode): string
    {
        return match ($countryCode) {
            'NG' => 'NGN',
            'CA' => 'CAD',
            default => 'USD',
        };
    }
}
