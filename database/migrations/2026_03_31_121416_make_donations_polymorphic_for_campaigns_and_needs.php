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
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->renameColumn('campaign_id', 'donatable_id');
            $table->string('donatable_type')->nullable()->after('id');
        });

        // Update existing records to link to Campaign
        \Illuminate\Support\Facades\DB::table('donations')->update([
            'donatable_type' => \App\Models\Campaign::class
        ]);

        Schema::table('donations', function (Blueprint $table) {
            $table->string('donatable_type')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->renameColumn('donatable_id', 'campaign_id');
            $table->dropColumn('donatable_type');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
        });
    }
};
