<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.exchangerate_api.key');
        $this->baseUrl = config('services.exchangerate_api.base_url');
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getExchangeRate($from, $to);

        return round($amount * $rate, 2);
    }

    /**
     * Get exchange rate between two currencies.
     */
    public function getExchangeRate(string $from, string $to): float
    {
        $cacheKey = "exchange_rate_{$from}_{$to}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($from, $to) {
            // Using a free tier endpoint if key is missing for demonstration,
            // but production should use the authenticated endpoint.
            $url = $this->apiKey 
                ? "{$this->baseUrl}{$this->apiKey}/pair/{$from}/{$to}"
                : "https://open.er-api.com/v6/latest/{$from}";

            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($this->apiKey) {
                    return (float) ($data['conversion_rate'] ?? 1);
                } else {
                    return (float) ($data['rates'][$to] ?? 1);
                }
            }

            return 1.0; // Default to 1:1 if API fails
        });
    }
}
