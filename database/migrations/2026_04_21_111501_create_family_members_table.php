<?php

use App\Enums\Profile\Gender;
use App\Enums\Family\Relationship;
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
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('family_members')->onDelete('cascade');
            $table->string('full_name');
            $table->date('dob');
            $table->string('gender'); // male, female (matching UpdateProfileRequest style)
            $table->string('photo')->nullable();
            $table->string('relationship'); // using string to store relationship from enum values
            $table->date('married_date')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->date('death_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
