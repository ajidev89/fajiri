<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Memberships are paid once and last for life, so plans no longer have a duration
     * and active memberships no longer expire or renew.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duration')->nullable()->comment('Unused: memberships are one-time lifetime payments')->change();
        });

        DB::table('plans')->update(['duration' => null]);

        DB::table('user_plans')
            ->where('status', 'active')
            ->update(['expires_at' => null, 'auto_renew' => false]);

        DB::table('user_plans')
            ->where('status', 'pending')
            ->update(['auto_renew' => false]);
    }

    public function down(): void
    {
        DB::table('plans')->whereNull('duration')->update(['duration' => 30]);

        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duration')->nullable(false)->comment('Duration in days')->change();
        });
    }
};
