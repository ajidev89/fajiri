<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Country;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * List all unique currencies supported by the countries.
     */
    public function index()
    {
        // Get unique currencies by country to include some context
        $currencies = Country::whereNotNull('currency')
            ->select('currency', 'name', 'iso2', 'iso3')
            ->groupBy('currency')
            ->get();

        return $this->handleSuccessCollectionResponse(
            "Successfully fetched supported currencies", 
            CurrencyResource::collection($currencies)
        );
    }

    /**
     * Get exchange rates relative to a base currency.
     */
    public function rates(Request $request)
    {
        $base = $request->query('base', 'NGN');
        
        $currencies = Country::whereNotNull('currency')
            ->pluck('currency')
            ->unique();

        $rates = [];
        foreach ($currencies as $currency) {
            $rates[$currency] = $this->currencyService->getExchangeRate($base, $currency);
        }

        return $this->handleSuccessResponse("Successfully fetched exchange rates", [
            'base' => $base,
            'rates' => $rates
        ]);
    }

    /**
     * Convert an amount between currencies.
     */
    public function convert(Request $request)
    {
        $request->validate([
            'from' => 'required|string|max:3',
            'to' => 'required|string|max:3',
            'amount' => 'required|numeric|min:0',
        ]);

        $result = $this->currencyService->convert(
            $request->amount, 
            $request->from, 
            $request->to
        );

        return $this->handleSuccessResponse("Successfully converted currency", [
            'from' => $request->from,
            'to' => $request->to,
            'amount' => (float) $request->amount,
            'result' => $result
        ]);
    }
}
