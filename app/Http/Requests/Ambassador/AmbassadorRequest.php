<?php

namespace App\Http\Requests\Ambassador;

use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class AmbassadorRequest extends ApiRequest
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
            'title' => ['required', 'string', 'max:255'],
            'biography' => ['required', 'string'],
            'photo' => [$this->isMethod('post') ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
