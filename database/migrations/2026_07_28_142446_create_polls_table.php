<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['radio', 'checkbox', 'short_text', 'long_text']);
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->dateTime('start_date');
            $table->unsignedInteger('duration_hours')->default(24);
            $table->foreignUuid('added_by')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
