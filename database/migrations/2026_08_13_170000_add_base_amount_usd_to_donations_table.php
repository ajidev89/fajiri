<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\CurrencyService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->decimal('base_amount_usd', 15, 2)->default(0.00)->after('converted_amount');
        });

        // Backfill base_amount_usd for existing donations
        try {
            $currencyService = app(CurrencyService::class);
            $donations = DB::table('donations')->get();

            foreach ($donations as $donation) {
                $currency = strtoupper($donation->currency ?? 'USD');
                $amount = (float) $donation->amount;

                $baseAmountUsd = $currency === 'USD'
                    ? $amount
                    : $currencyService->convert($amount, $currency, 'USD');

                DB::table('donations')
                    ->where('id', $donation->id)
                    ->update(['base_amount_usd' => round($baseAmountUsd, 2)]);
            }
        } catch (\Throwable $e) {
            // Ignore if currency service isn't reachable during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('base_amount_usd');
        });
    }
};
