<?php

namespace App\Http\Requests\Auth;

use App\Enums\Profile\Gender;
use App\Enums\User\AccountType;
use App\Enums\User\SubAccountType;
use App\Http\Requests\ApiRequest;
use App\Rules\ValidatePhoneNumber;
use App\Rules\ValidateToken;
use Illuminate\Validation\Rules\Enum;

class RegisterRequest extends ApiRequest
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
            "country_id" => "required|exists:countries,id",
            "first_name" => "required",
            "last_name"  => "required",
            "email.value" => [
                "nullable",
                "email",
                "unique:users,email",
            ],
            "email.token" => [
                "nullable",
                new ValidateToken(),
            ],
            "phone.value" => [
                "required",
                "unique:users,phone",
                new ValidatePhoneNumber(),
            ],
            "phone.token" => [
                "nullable",
                new ValidateToken(),
            ],
            'dob' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
            ],
            "gender" => ["nullable", new Enum(Gender::class)],
            "account_type" => ["nullable", new Enum(AccountType::class)],
            "sub_account_type" => ["nullable"],
            "address" => "required",
            "occupation" => "required",
            "avatar" => "nullable",
            "password" => "required|confirmed|min:8",
            "referral_code" => "nullable|exists:users,member_id"
        ];
    }
}
