<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\ApiRequest;

class CampaignRequest extends ApiRequest
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
            'currency' => 'required|string|exists:countries,currency',
            'images' => 'nullable|array',
            'images.*' => 'string|url', // Assuming images are URLs
            'goal_amount' => 'required|numeric|min:0.01',
        ];
    }
}
