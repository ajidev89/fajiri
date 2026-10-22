<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $column = collect(Schema::getColumns('sessions'))->firstWhere('name', 'user_id');
        $type = strtolower((string) ($column['type_name'] ?? ''));

        if (in_array($type, ['uuid', 'char', 'varchar', 'string'], true)) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }
};
