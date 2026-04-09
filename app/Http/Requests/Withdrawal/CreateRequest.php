<?php

namespace App\Http\Requests\Withdrawal;

use App\Http\Requests\ApiRequest;

class CreateRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_number' => 'required|string',
            'bank_code' => 'required|string',
            'account_name' => 'required|string',
            'pin' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.required' => 'Account number is required',
            'bank_code.required' => 'Bank code is required',
            'account_name.required' => 'Account name is required',
            'pin.required' => 'Pin is required',
        ];
    }
}
