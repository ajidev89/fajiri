<?php

use App\Enums\Donations\Medium;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $cases = implode("','", Medium::values());

        DB::statement("ALTER TABLE donations MODIFY COLUMN medium ENUM('{$cases}') NOT NULL DEFAULT 'wallet'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE donations MODIFY COLUMN medium ENUM('paystack', 'wallet', 'stripe', 'paypal') NOT NULL DEFAULT 'wallet'");
    }
};
