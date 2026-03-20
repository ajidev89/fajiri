<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\Insurance\Type;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('insurances', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->string("name");
            $table->string("slug");
            $table->string("website");
            $table->string("logo");
            $table->string("phone")->nullable();
            $table->string("email")->nullable();
            $table->string("address");
            $table->string("description")->nullable();
            $table->enum("type", Type::values());
            $table->string("city");
            $table->string('state');
            $table->foreignId("country_id")->constrained()->cascadeOnDelete();
            $table->enum("status", ["active", "inactive"])->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurances');
    }
};
