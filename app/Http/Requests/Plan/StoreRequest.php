<?php

namespace App\Http\Requests\Plan;

use App\Enums\User\AccountType;
use App\Enums\User\SubAccountType;
use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

class StoreRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:plans,name',
            'level' => 'required|string|max:255',
            'account_type' => ['required', new Enum(AccountType::class)],
            'sub_account_type' => ['nullable', new Enum(SubAccountType::class)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'features' => 'nullable|array',
            'status' => 'required|boolean',
        ];
    }
}
