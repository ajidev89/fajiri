<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Temporarily change the column to string so we can support the new value type easily
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->change();
        });

        // 2. Update existing data in users and plans tables
        DB::table('users')
            ->where('account_type', 'project-membership')
            ->update(['account_type' => 'program-membership']);

        DB::table('plans')
            ->where('account_type', 'project-membership')
            ->update(['account_type' => 'program-membership']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('account_type', 'program-membership')
            ->update(['account_type' => 'project-membership']);

        DB::table('plans')
            ->where('account_type', 'program-membership')
            ->update(['account_type' => 'project-membership']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->change();
        });
    }
};
