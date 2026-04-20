<?php

use App\Models\Role;
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
        Role::firstOrCreate(['name' => 'Fundraiser'], ['slug' => 'fundraiser']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::where('slug', 'fundraiser')->delete();
    }
};
