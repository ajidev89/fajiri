<?php

namespace App\Http\Requests\Family;

use App\Enums\Family\Relationship;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class FamilyMemberRequest extends FormRequest
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
        return [
            'parent_id' => 'nullable|exists:family_members,id',
            'full_name' => 'required|string|max:255',
            'dob' => 'required|date',
            'gender' => 'required|string|in:male,female',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'relationship' => $this->isMethod('post')
                ? ['required', new Enum(Relationship::class), Rule::notIn([Relationship::ME->value])]
                : ['required', new Enum(Relationship::class)],
            'married_date' => 'nullable|date',
            'is_alive' => 'required|boolean',
            'death_date' => 'nullable|date|required_if:is_alive,false',
            'note' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'relationship.not_in' => 'The account holder is already in the family tree.',
            'relationship.required' => 'The relationship field is required.',
            'full_name.required' => 'The full name field is required.',
            'dob.required' => 'The date of birth field is required.',
            'gender.required' => 'The gender field is required.',
            'is_alive.required' => 'The living status field is required.',
            'death_date.required_if' => 'The death date is required when the member is not alive.',
        ];
    }
}
