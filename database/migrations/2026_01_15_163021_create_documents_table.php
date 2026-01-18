<?php

use App\Enums\Document\Type;
use App\Enums\Kyc\Provider;
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
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained()->cascadeOnDelete();
            $table->enum("provider", Provider::values())->default(Provider::VERIFF->value);
            $table->foreignUuid("verification_session_id")->nullable();
            $table->enum("type", Type::values());
            $table->string("url");
            $table->string("name");
            $table->string("mimetype");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
