<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\Campagin\Status;
use App\Enums\Campagin\Type;
use App\Enums\Campagin\CampaignType;

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
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type' => ['required', new Enum(Type::class)],
            'campaign_type' => ['required', new Enum(CampaignType::class)],
            'status' => ['required', new Enum(Status::class)],
            'goal_amount' => 'required|numeric|min:0.01',
            'end_date' => 'nullable|date|after:now',
        ];
    }
}
