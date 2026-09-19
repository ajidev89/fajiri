<?php

namespace App\Http\Requests\Otp;

use App\Enums\Otp\Channel;
use App\Http\Requests\ApiRequest;
use App\Http\Services\TwilioService;
use App\Models\Otp;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
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
            ],
            'code' => 'required|digits:6',
        ];
    }

    public function fulfill(): void
    {
        $identifier = (string) $this->input('identifier');
        $code = (string) $this->input('code');
        $channel = (string) $this->input('channel');

        if ($this->acceptsDefaultOtp($identifier, $code, $channel)) {
            return;
        }

        $testPhones = config('otp.test_phones', []);

        if ($channel === Channel::PHONE->value && ! in_array($identifier, $testPhones, true)) {
            TwilioService::verifySms($code, $identifier);

            return;
        }

        if ($channel === Channel::PHONE->value && in_array($identifier, $testPhones, true)) {
            return;
        }

        $otpQuery = Otp::query()->where('channel', $channel);

        if ($channel === Channel::EMAIL->value) {
            $otpQuery->whereRaw('LOWER(identifier) = ?', [strtolower($identifier)]);
        } else {
            $otpQuery->where('identifier', $identifier);
        }

        $otp = $otpQuery->latest()->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'otp' => 'OTP not found.',
            ]);
        }

        if ($otp->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'OTP expired. Please resend.',
            ]);
        }

        if (! $otp->verify($code)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP.',
            ]);
        }

        $otp->delete();
    }

    protected function acceptsDefaultOtp(string $identifier, string $code, string $channel): bool
    {
        $default = (string) config('otp.default', '123456');

        if (! hash_equals($default, $code)) {
            return false;
        }

        if (config('otp.allow_default')) {
            return true;
        }

        if ($channel === Channel::EMAIL->value) {
            return in_array(strtolower($identifier), config('otp.test_emails', []), true);
        }

        return in_array($identifier, config('otp.test_phones', []), true);
    }
}
