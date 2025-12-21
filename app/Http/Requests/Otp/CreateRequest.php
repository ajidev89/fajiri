<?php

namespace App\Http\Requests\Otp;

use App\Enums\Otp\Channel;
use App\Http\Requests\ApiRequest;
use App\Rules\ValidatePhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateRequest extends ApiRequest
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
            'channel' => ['required', new Enum(Channel::class)],
            'identifier' => [
                'required',
                Rule::when(
                    request('channel') === Channel::EMAIL->value,
                    ['email']
                ),

                Rule::when(
                    request('channel') === Channel::PHONE->value,
                    new ValidatePhoneNumber() // or your ValidatePhoneNumber rule
                ),
            ],
        ];
    }
}
