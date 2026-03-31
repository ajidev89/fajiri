<?php

use App\Enums\Need\Urgency;
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
        Schema::create('needs', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->string("name");
            $table->string("age")->nullable();
            $table->string("location");
            $table->string('currency', 3)->nullable();
            $table->decimal("amount", 10, 2)->default(0.00);
            $table->mediumText("description");
            $table->string("image")->nullable();
            $table->enum("urgency", Urgency::values());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('needs');
    }
};
