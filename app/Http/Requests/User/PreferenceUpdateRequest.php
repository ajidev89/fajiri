<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;

class PreferenceUpdateRequest extends ApiRequest
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
            'notification_sound' => 'sometimes|boolean',
            'auto_update_software' => 'sometimes|boolean',
            'community_updates' => 'sometimes|boolean',
            'project_updates' => 'sometimes|boolean',
            'event_updates' => 'sometimes|boolean',
            'receive_payment_confirmation' => 'sometimes|boolean',
            'membership_status_updates' => 'sometimes|boolean',
        ];
    }
}
