<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CreatePinRequest extends ApiRequest
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
    $user = auth()->user();

    return [
        'pin' => 'required|string|size:4|confirmed',

        'current_pin' => [
            'nullable',
            'string',
            'size:4',
            Rule::requiredIf(fn () => !is_null($user->pin))
        ],
    ];
}

    public function messages(): array
    {
        return [
            'pin.required' => 'Pin is required',
            'pin.size' => 'Pin must be 4 digits',
            'pin.confirmed' => 'Pin does not match',
            'current_pin.required_if' => 'Current pin is required',
            'current_pin.size' => 'Current pin must be 4 digits',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = auth()->user();

            if ($user->pin) {
                if (!Hash::check($this->current_pin, $user->pin)) {
                    $validator->errors()->add('current_pin', 'Current pin is incorrect');
                }
            }
        });
    }
}
