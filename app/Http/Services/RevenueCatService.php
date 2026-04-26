<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevenueCatService
{
    protected $baseUrl = 'https://api.revenuecat.com/v2';
    protected $apiKey;
    protected $projectId;

    public function __construct()
    {
        $this->apiKey = config('services.revenuecat.api_key');
        $this->projectId = config('services.revenuecat.project_id');
    }

    protected function headers()
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function ensureEntitlementExists($id, $displayName = 'Premium')
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/entitlements", [
                'lookup_key' => $id,
                'display_name' => $displayName,
            ]);

        return $response->successful() || $response->status() === 409;
    }

    public function ensureOfferingExists($id, $displayName = 'Default Offering')
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/offerings", [
                'lookup_key' => $id,
                'display_name' => $displayName,
            ]);

        return $response->successful() || $response->status() === 409;
    }

    /**
     * Register a product in RevenueCat following v2 spec
     */
    public function registerProduct($storeIdentifier, $displayName, $appId, $durationDays = null)
    {
        if (!$appId) return null;

        $payload = [
            'app_id' => $appId,
            'store_identifier' => $storeIdentifier,
            'display_name' => $displayName,
            'type' => 'subscription', // Default to subscription
        ];

        if ($durationDays) {
            $payload['subscription'] = [
                'duration' => $this->mapDaysToISO8601($durationDays)
            ];
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/products", $payload);

        if ($response->successful()) {
            return $response->json()['id'];
        }

        if ($response->status() === 409) {
            // Already exists, return the ID if provided in error or fallback
            return $response->json()['id'] ?? $storeIdentifier; 
        }

        Log::error('RevenueCat Create Product Failed', ['response' => $response->json(), 'payload' => $payload]);
        return null;
    }

    protected function mapDaysToISO8601($days)
    {
        if ($days >= 365) return 'P1Y';
        if ($days >= 180) return 'P6M';
        if ($days >= 90) return 'P3M';
        if ($days >= 30) return 'P1M';
        if ($days >= 7) return 'P1W';
        return 'P' . $days . 'D';
    }

    public function createPackage($offeringId, $lookupKey, $displayName)
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/offerings/{$offeringId}/packages", [
                'lookup_key' => $lookupKey,
                'display_name' => $displayName,
            ]);

        if ($response->successful() || $response->status() === 409) {
            return $lookupKey;
        }

        return null;
    }

    public function attachProductToPackage($offeringId, $packageId, $productId)
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/offerings/{$offeringId}/packages/{$packageId}/products", [
                'product_id' => $productId,
            ])->successful();
    }

    public function linkProductToEntitlement($entitlementId, $productId)
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/entitlements/{$entitlementId}/products", [
                'product_id' => $productId,
            ])->successful();
    }
}
