<?php

namespace App\Http\Requests\Otp;

use App\Enums\Otp\Channel;
use App\Http\Requests\ApiRequest;
use App\Http\Services\TwilioService;
use App\Models\Otp;
use App\Rules\ValidatePhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class VerifyRequest extends ApiRequest
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
            'channel' => ['required', new Enum(Channel::class)],
            'identifier' => [
                'required',
                Rule::when(
                    request('channel') === Channel::EMAIL->value,
                    ['email']
                ),

                Rule::when(
                    request('channel') === Channel::PHONE->value,
                    new ValidatePhoneNumber() // or your ValidatePhoneNumber rule
                ),
            ],
            'code' => "required|digits:6"
        ];
    }

    public function fulfill()
    {
        $identifier = $this->input('identifier');
        $code = $this->input('code');

        $phone = ["+2349063328998"];

        // Skip Twilio verification for test numbers
        if (!in_array($identifier, $phone)) {
            if ($this->input('channel') === Channel::PHONE->value) {
               return TwilioService::verifySms($code, $identifier);
            }
        }

        $emails = [];

        if (in_array($identifier, $emails)) { 
            return;
        }

        $otp = Otp::where('identifier', $identifier)->latest()->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'otp' => 'OTP not found.'
            ]);
        }

        if ($otp->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'OTP expired. Please resend.'
            ]);
        }

        if (!$otp->verify($code)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP.'
            ]);
        }

        $otp->delete();
    }
}
