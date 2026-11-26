<?php

namespace App\Http\Requests\Withdrawal;

use App\Http\Requests\ApiRequest;

class WithdrawRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:'.max(0.01, (float) config('withdrawals.minimum_amount')),
            'pin' => 'required|string',
            'note' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum withdrawal is '.number_format(max(0.01, (float) config('withdrawals.minimum_amount')), 2),
        ];
    }
}
