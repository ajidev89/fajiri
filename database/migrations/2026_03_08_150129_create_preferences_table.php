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
        Schema::create('preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notification_sound')->default(true);
            $table->boolean('auto_update_software')->default(true);
            $table->boolean('community_updates')->default(true);
            $table->boolean('project_updates')->default(true);
            $table->boolean('event_updates')->default(true);
            $table->boolean('receive_payment_confirmation')->default(true);
            $table->boolean('membership_status_updates')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};
