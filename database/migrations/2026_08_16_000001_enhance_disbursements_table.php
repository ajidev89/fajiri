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
        Schema::table('disbursements', function (Blueprint $table) {
            $table->string('disbursement_code')->nullable()->unique()->after('id');
            $table->string('recipient_type')->default('campaign_owner')->after('disbursed_by');
            $table->string('recipient_country')->default('NG')->after('recipient_type');
            $table->string('recipient_email')->nullable()->after('recipient_country');
            $table->string('recipient_phone')->nullable()->after('recipient_email');
            $table->string('destination_mask')->nullable()->after('account_number');
            $table->string('bank_code')->nullable()->after('bank_name');
            $table->string('routing_number')->nullable()->after('bank_code');
            $table->string('swift_bic')->nullable()->after('routing_number');
            $table->string('iban')->nullable()->after('swift_bic');
            $table->decimal('fee_amount', 15, 2)->default(0)->after('amount');
            $table->string('fee_bearer')->default('campaign')->after('fee_amount');
            $table->decimal('net_amount', 15, 2)->nullable()->after('fee_bearer');
            $table->string('target_currency')->nullable()->after('currency');
            $table->decimal('estimated_recipient_amount', 15, 2)->nullable()->after('rate');
            $table->string('purpose')->nullable()->after('status');
            $table->text('purpose_description')->nullable()->after('purpose');
            $table->json('documents')->nullable()->after('purpose_description');
            $table->json('compliance_checks')->nullable()->after('documents');
            $table->integer('risk_score')->default(0)->after('compliance_checks');
            $table->string('risk_level')->default('low')->after('risk_score');
            $table->string('security_auth_method')->default('password')->after('risk_level');
            $table->string('payout_provider')->nullable()->after('security_auth_method');
            $table->string('provider_reference')->nullable()->after('payout_provider');
            $table->json('provider_response')->nullable()->after('provider_reference');
            $table->json('status_history')->nullable()->after('rejected_reason');
            $table->json('audit_trail')->nullable()->after('status_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropColumn([
                'disbursement_code',
                'recipient_type',
                'recipient_country',
                'recipient_email',
                'recipient_phone',
                'destination_mask',
                'bank_code',
                'routing_number',
                'swift_bic',
                'iban',
                'fee_amount',
                'fee_bearer',
                'net_amount',
                'target_currency',
                'estimated_recipient_amount',
                'purpose',
                'purpose_description',
                'documents',
                'compliance_checks',
                'risk_score',
                'risk_level',
                'security_auth_method',
                'payout_provider',
                'provider_reference',
                'provider_response',
                'status_history',
                'audit_trail',
            ]);
        });
    }
};
