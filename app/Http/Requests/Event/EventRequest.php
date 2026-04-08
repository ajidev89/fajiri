<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\ApiRequest;

class EventRequest extends ApiRequest
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
        $isPost = $this->isMethod('post');

        return [
            'category_id' => 'nullable|exists:categories,id',
            'title' => $isPost ? 'required|string|max:255' : 'nullable|string|max:255',
            'description' => $isPost ? 'required|string' : 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_date' => $isPost ? 'required|date' : 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled',
            'is_featured' => 'nullable|boolean',
        ];
    }
}
