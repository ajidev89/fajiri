<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;

class TransferRequest extends ApiRequest
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
            "username" => "required|string|exists:users,username|not_in:" . auth()->user()->username,
            "amount" => "required|numeric",
            "pin" => "required|numeric",
        ];
    }
}
