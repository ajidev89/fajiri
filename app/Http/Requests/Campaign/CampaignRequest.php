<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'string|url', // Assuming images are URLs
            'goal_amount' => 'required|numeric|min:0.01',
        ];
    }
}
