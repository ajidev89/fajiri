<?php

namespace App\Http\Requests\Need;

use App\Enums\Need\Urgency;
use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|exists:countries,currency',
            'urgency' => ['required', Rule::in(Urgency::values())],
            'location' => 'required|string|max:255',
            'age' => 'nullable|integer|min:0',
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240';
        } else {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:10240';
        }

        return $rules;
    }
}
