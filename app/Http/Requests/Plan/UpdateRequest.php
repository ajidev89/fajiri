<?php

namespace App\Http\Requests\Plan;

use App\Enums\User\AccountType;
use App\Enums\User\SubAccountType;
use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

class UpdateRequest extends ApiRequest
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
            'name' => 'sometimes|string|max:255',
            'level' => 'sometimes|string|max:255',
            'account_type' => ['sometimes', new Enum(AccountType::class)],
            'sub_account_type' => ['nullable', new Enum(SubAccountType::class)],
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'features' => 'sometimes|array',
            'status' => 'sometimes|boolean',
        ];
    }
}
