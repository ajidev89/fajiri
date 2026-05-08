<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_plans', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('status');
            $table->string('provider_subscription_id')->nullable()->after('provider');
            $table->string('payment_method')->nullable()->after('provider_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_plans', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_subscription_id', 'payment_method']);
        });
    }
};
