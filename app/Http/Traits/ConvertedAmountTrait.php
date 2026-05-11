<?php

namespace App\Http\Traits;

use App\Services\CurrencyService;

trait ConvertedAmountTrait
{
    use AuthUserTrait;
    /**
     * Convert an amount to the detected currency from the request.
     */
    protected function getConvertedAmount($amount, ?string $sourceCurrency, $request): array
    {
        $currencyService = app(CurrencyService::class);
        $targetCurrency = $request->detected_currency ?? 'USD';
        $sourceCurrency = $sourceCurrency ?? 'NGN';

        // Exempt Admin from conversion
        if ($this->user() && $this->user()->role && in_array($this->user()->role->slug, ['admin', 'super-admin'])) {
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
