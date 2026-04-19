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
        Schema::create('disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('disbursable');
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('NGN');
            $table->decimal('converted_amount', 15, 2)->nullable();
            $table->decimal('rate', 15, 8)->nullable();
            $table->string('beneficiary_name');
            $table->string('payment_method');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('status')->default('pending');
            $table->string('proof_of_payment')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
