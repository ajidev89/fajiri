<?php

namespace App\Http\Requests\Plan;

use App\Http\Requests\ApiRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:plans,name',
            'level' => 'required|string|max:255',
            'account_type' => ['required', new \Illuminate\Validation\Rules\Enum(\App\Enums\User\AccountType::class)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'duration' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'status' => 'nullable|boolean',
            'rc_product_id_ios' => 'nullable|string|max:255',
            'rc_product_id_android' => 'nullable|string|max:255',
        ];
    }
}
