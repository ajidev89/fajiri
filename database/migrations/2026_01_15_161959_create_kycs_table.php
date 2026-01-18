<?php

use App\Enums\Kyc\Provider;
use App\Enums\Kyc\Status;
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
        Schema::create('kycs', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained()->cascadeOnDelete();
            $table->enum("provider", Provider::values())->default(Provider::VERIFF->value);
            $table->foreignUuid("verification_session_id")->nullable();
            $table->enum("status", Status::values())->default(Status::NOT_STARTED->value);
            $table->dateTimeTz("verified_at")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
