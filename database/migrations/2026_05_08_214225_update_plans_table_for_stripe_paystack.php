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
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('currency');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
            $table->string('paystack_plan_code')->nullable()->after('stripe_price_id');
            
            $table->dropColumn([
                'rc_entitlement_id',
                'rc_offering_id',
                'rc_package_id',
                'rc_product_id_ios',
                'rc_product_id_android'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id', 'paystack_plan_code']);
            
            $table->string('rc_entitlement_id')->nullable();
            $table->string('rc_offering_id')->nullable();
            $table->string('rc_package_id')->nullable();
            $table->string('rc_product_id_ios')->nullable();
            $table->string('rc_product_id_android')->nullable();
        });
    }
};
