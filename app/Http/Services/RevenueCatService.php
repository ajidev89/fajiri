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

    public function createPackage($offeringId, $lookupKey, $displayName)
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/offerings/{$offeringId}/packages", [
                'lookup_key' => $lookupKey,
                'display_name' => $displayName,
            ]);

        if ($response->successful() || $response->status() === 409) {
            return ['id' => $lookupKey];
        }

        Log::error('RevenueCat Create Package Failed', ['response' => $response->json(), 'offering' => $offeringId]);
        return null;
    }

    public function registerProduct($appId, $storeIdentifier, $displayName, $type = 'subscription')
    {
        // App ID is the RevenueCat App ID (e.g. app123)
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/products", [
                'app_id' => $appId,
                'store_identifier' => $storeIdentifier,
                'display_name' => $displayName,
                'type' => $type,
            ]);

        if ($response->successful() || $response->status() === 409) {
            // Note: v2 API might return a generated 'prod_xxx' ID or we use store_identifier
            return $response->json();
        }

        Log::error('RevenueCat Register Product Failed', ['response' => $response->json()]);
        return null;
    }

    public function linkProductToEntitlement($entitlementId, $productId)
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/entitlements/{$entitlementId}/products", [
                'product_id' => $productId,
            ]);

        return $response->successful() || $response->status() === 409;
    }

    public function attachProductToPackage($offeringId, $packageId, $productId)
    {
        // In some API versions, this might be a different endpoint.
        // Assuming v2 allows linking products to packages:
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/projects/{$this->projectId}/offerings/{$offeringId}/packages/{$packageId}/products", [
                'product_id' => $productId,
            ]);

        return $response->successful() || $response->status() === 409;
    }
}
