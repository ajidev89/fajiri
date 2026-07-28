<?php

namespace App\Http\Requests\Poll;

use App\Enums\Poll\PollType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class PollRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $optionRequired = in_array($type, [PollType::RADIO->value, PollType::CHECKBOX->value]);

        return [
            'title'           => 'required|string|max:255',
            'type'            => ['required', 'string', Rule::in(PollType::values())],
            'status'          => ['nullable', 'string', Rule::in(['draft', 'active', 'inactive'])],
            'start_date'      => 'required|date',
            'duration_hours'  => 'required|integer|min:1|max:8760',
            'options'         => [Rule::requiredIf($optionRequired), 'array', 'min:2'],
            'options.*.label' => 'required_with:options|string|max:255',
        ];
    }
}
