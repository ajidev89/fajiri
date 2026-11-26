<?php

namespace App\Http\Requests\Withdrawal;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Bank account an admin will pay by hand. Works for any country:
 * - local: account_number (+ bank_code / routing_number / sort_code as the country needs)
 * - iban:  iban + swift_code (Europe, UK, Middle East, ...)
 * - swift: account_number + swift_code (international wire)
 */
class CreateRequest extends ApiRequest
{
    public const ACCOUNT_TYPES = ['local', 'iban', 'swift'];

    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->merge([
            'account_type' => $this->input('account_type', 'local'),
            'country' => strtoupper((string) $this->input('country', $user?->country?->iso2 ?? '')),
            'currency' => strtoupper((string) $this->input('currency', $user?->wallet?->currency ?? $user?->country?->currency ?? '')),
            'iban' => $this->filled('iban') ? strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban'))) : null,
            'swift_code' => $this->filled('swift_code') ? strtoupper(trim((string) $this->input('swift_code'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => 'required|string',
            'account_type' => ['required', Rule::in(self::ACCOUNT_TYPES)],
            'country' => 'required|string|size:2',
            'currency' => 'required|string|size:3',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required_unless:account_type,iban|nullable|string|max:64',
            'bank_code' => 'nullable|string|max:64',
            'routing_number' => 'nullable|string|max:64',
            'sort_code' => 'nullable|string|max:32',
            'iban' => ['required_if:account_type,iban', 'nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/'],
            'swift_code' => ['required_if:account_type,iban,swift', 'nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'bank_address' => 'nullable|string|max:500',
            'account_holder_address' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required' => 'Pin is required',
            'account_name.required' => 'Account name is required',
            'bank_name.required' => 'Bank name is required',
            'account_number.required_unless' => 'Account number is required',
            'iban.required_if' => 'IBAN is required for IBAN accounts',
            'iban.regex' => 'IBAN is not valid',
            'swift_code.required_if' => 'SWIFT/BIC code is required for international accounts',
            'swift_code.regex' => 'SWIFT/BIC code is not valid',
            'country.required' => 'Country is required',
            'currency.required' => 'Currency is required',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountData(): array
    {
        return collect($this->validated())->except('pin')->all();
    }
}
