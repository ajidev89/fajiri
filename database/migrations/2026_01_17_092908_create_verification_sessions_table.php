<?php

use App\Enums\Kyc\Provider;
use App\Enums\Verification\Status;
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
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained()->cascadeOnDelete();
            $table->enum("provider", Provider::values())->default(Provider::VERIFF->value);
            $table->string("session_id")->nullable();
            $table->enum("status", Status::values())->default(Status::CREATED->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_sessions');
    }
};
