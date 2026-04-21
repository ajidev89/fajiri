<?php

namespace App\Http\Requests\Family;

use App\Enums\Family\Relationship;
use Illuminate\Foundation\Http\FormRequest;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|exists:family_members,id',
            'full_name' => 'required|string|max:255',
            'dob' => 'required|date',
            'gender' => 'required|string|in:male,female',
            'photo' => 'nullable|string',
            'relationship' => ['required', new Enum(Relationship::class)],
            'married_date' => 'nullable|date',
            'is_alive' => 'required|boolean',
            'death_date' => 'nullable|date|required_if:is_alive,false',
            'note' => 'nullable|string',
        ];
    }
}
