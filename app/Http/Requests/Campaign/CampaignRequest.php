<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Campagin\CampaignType;
use App\Enums\Campagin\Status;
use App\Enums\Campagin\Type;
use App\Http\Requests\ApiRequest;
use App\Models\Category;
use Illuminate\Validation\Rules\Enum;

class CampaignRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * When a category is chosen but no legacy `type` is sent, derive `type`
     * from the category slug (e.g. "education") so older clients that read
     * `type` keep working. Anything that doesn't match falls back to "other".
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('category_id') && ! $this->filled('type')) {
            $slug = Category::whereKey($this->input('category_id'))->value('slug');

            $this->merge([
                'type' => (Type::tryFrom((string) $slug) ?? Type::OTHER)->value,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'currency' => 'required|string|exists:countries,currency',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id' => 'nullable|integer|exists:categories,id',
            'type' => ['required', new Enum(Type::class)],
            'campaign_type' => ['required', new Enum(CampaignType::class)],
            'age' => 'required_if:campaign_type,personal|integer|min:0|max:120',
            'location' => 'required_if:campaign_type,personal|string|max:255',
            'status' => ['required', new Enum(Status::class)],
            'is_urgent' => 'sometimes|boolean',
            'goal_amount' => 'required|numeric|min:0.01',
            'days' => 'required|integer|min:1',
            'end_date' => 'nullable|date|after:now',
        ];
    }
}
