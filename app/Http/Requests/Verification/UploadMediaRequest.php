<?php

namespace App\Http\Requests\Verification;

use App\Enums\Document\Type;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Enum;

class UploadMediaRequest extends ApiRequest
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
            "image" => "required|file",
            "type" => ["required", new Enum(Type::class)],
        ];
    }
}
