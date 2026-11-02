<?php

namespace App\Http\Requests\Insurance;

use App\Enums\Insurance\Type;
use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

class CreateRequest extends ApiRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'slug' => 'required|unique:insurances,slug,'.$this->id,
            'website' => 'required',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'phone' => 'nullable',
            'email' => 'nullable|email',
            'address' => 'required',
            'type' => ['required', new Enum(Type::class)],
            'description' => 'sometimes',
            'city' => 'required',
            'state' => 'required',
            'country_id' => 'required|exists:countries,id',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
