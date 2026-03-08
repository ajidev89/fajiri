<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\ApiRequest;

class DonationRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'email' => auth()->check() ? 'nullable|email' : 'required|email',
        ];
    }
}
