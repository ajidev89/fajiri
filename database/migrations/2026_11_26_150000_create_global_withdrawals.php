<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Withdrawals are requests that an admin pays manually, so bank accounts can be
     * in any country and no longer need a Paystack or Stripe recipient.
     */
    public function up(): void
    {
        Schema::table('withdrawal_accounts', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('user_id');
            $table->string('currency', 3)->nullable()->after('country');
            $table->string('account_type')->default('local')->after('currency');
            $table->string('bank_code')->nullable()->after('bank_name');
            $table->string('iban')->nullable()->after('routing_number');
            $table->string('swift_code')->nullable()->after('iban');
            $table->string('sort_code')->nullable()->after('swift_code');
            $table->string('bank_address')->nullable()->after('sort_code');
            $table->string('account_holder_address')->nullable()->after('bank_address');
        });

        // Paystack accounts stored their bank code in routing_number.
        DB::table('withdrawal_accounts')->orderBy('id')->each(function ($account) {
            $meta = json_decode((string) $account->meta, true) ?: [];

            if (($meta['provider'] ?? null) === 'paystack') {
                DB::table('withdrawal_accounts')->where('id', $account->id)->update([
                    'bank_code' => $account->routing_number,
                    'routing_number' => null,
                ]);
            }
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('withdrawal_account_id')->nullable();
            $table->foreignUuid('transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->json('account_snapshot');
            $table->foreignUuid('reviewed_by')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('proof_url')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');

        Schema::table('withdrawal_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'country', 'currency', 'account_type', 'bank_code', 'iban',
                'swift_code', 'sort_code', 'bank_address', 'account_holder_address',
            ]);
        });
    }
};
