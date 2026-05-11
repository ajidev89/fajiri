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
        
        info("targert_currency",$targetCurrency);
        info("source_currency",[$sourceCurrency]);


        // Exempt Admin from conversion

        info("user",[$this->user()]);
        info($this->user()->role);
        info($this->user()->role->slug === 'admin' || $this->user()->role->slug === 'super-admin');
        
        if ($this->user() && $this->user()->role && ($this->user()->role->slug === 'admin' || $this->user()->role->slug === 'super-admin')) {
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
