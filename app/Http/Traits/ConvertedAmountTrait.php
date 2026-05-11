<?php

namespace App\Http\Traits;

use App\Services\CurrencyService;

trait ConvertedAmountTrait
{
    /**
     * Convert an amount to the detected currency from the request.
     */
    protected function getConvertedAmount($amount, ?string $sourceCurrency, $request): array
    {
        $currencyService = app(CurrencyService::class);
        $targetCurrency = $request->detected_currency ?? 'USD';
        $sourceCurrency = $sourceCurrency ?? 'NGN';


        // Exempt Admin from conversion
        $user = $request->user();

        info($user);
        info($user->role->slug);
        info($user->role->slug === 'admin' || $user->role->slug === 'super-admin');
        
        if ($user && $user->role && ($user->role->slug === 'admin' || $user->role->slug === 'super-admin')) {
            return [
                'amount' => (float) $amount,
                'currency' => $sourceCurrency,
                'base_amount' => (float) $amount,
                'base_currency' => $sourceCurrency,
            ];
        }
        
        $convertedAmount = $currencyService->convert(
            (float) $amount,
            $sourceCurrency,
            $targetCurrency
        );

        return [
            'amount' => $convertedAmount,
            'currency' => $targetCurrency,
            'base_amount' => (float) $amount,
            'base_currency' => $sourceCurrency,
        ];
    }
}
