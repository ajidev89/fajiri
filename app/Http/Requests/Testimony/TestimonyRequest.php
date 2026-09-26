<?php

namespace App\Http\Requests\Testimony;

use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class TestimonyRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'story' => ['required', 'string'],
            'photo' => [$this->isMethod('post') ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
