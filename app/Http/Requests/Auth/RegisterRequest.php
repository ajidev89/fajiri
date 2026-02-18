<?php

namespace App\Http\Requests\Auth;

use App\Enums\Profile\Gender;
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
            "first_name" => "required",
            "last_name"  => "required",
            "email.value" => "required|email|unique:users,email",
            "email.token" => ["required", new ValidateToken()],
            "phone.value" => ["required", 
            // new ValidatePhoneNumber(), 
            "unique:users,phone"],
            "phone.token" => ["required", new ValidateToken()],
            'dob' => [
                'required',
                'date', 
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
            ],
            // "gender" => ["required", new Enum(Gender::class)],
            "address" => "required",
            "occupation" => "required",
            "avatar" => "nullable",
            "password" => "required|confirmed|min:8"
        ];
    }
}
