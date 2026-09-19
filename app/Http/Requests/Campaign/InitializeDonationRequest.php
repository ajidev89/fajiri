<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Donations\Medium;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class InitializeDonationRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $gateways = array_values(array_filter(
            Medium::values(),
            fn (string $gateway) => $gateway !== Medium::WALLET->value
        ));

        return [
            'amount' => 'required|numeric|min:1',
            'gateway' => ['nullable', 'string', Rule::in($gateways)],
            'email' => auth()->check() ? 'nullable|email' : 'required|email',
            'name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Donation amount is required.',
            'gateway.in' => 'The selected payment gateway is invalid.',
            'email.required' => 'Email is required for guest donations.',
        ];
    }
}
