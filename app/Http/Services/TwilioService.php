<?php

namespace App\Http\Services;

use Exception;
use Twilio\Exceptions\TwilioException;

class TwilioService
{
    public static function sendSms($number, $message)
    {
        try {
            return app('twilio')->messages->create(
                $number,
                [
                    'from' => config('services.twilio.phone_number'),
                    'body' => $message,
                ]
            );
        } catch (TwilioException $e) {
            info($e->getMessage());
            throw new Exception($e->getMessage());
        }

    }

    public static function sendVerificationSms($number)
    {
        try {
            $response = app('twilio')->verify->v2->services(config('services.twilio.smssid'))
                ->verifications
                ->create($number, 'sms');

            info($response);

            return $response;

        } catch (TwilioException $e) {
            info($e->getMessage());
            throw new Exception($e->getMessage());
        }

    }

    public static function verifySms($code, $number)
    {
        try {

            $response = app('twilio')->verify->v2->services(config('services.twilio.smssid'))->verificationChecks->create(['to' => $number, 'code' => $code]);

            if ($response->status === 'approved') {
                return $response;
            } else {
                throw new Exception('OTP does not match');
            }

            return $response;

        } catch (TwilioException $e) {
            info($e->getMessage());
            throw new Exception($e->getMessage());
        }

    }

    public static function validate(string $phoneNumber): bool
    {
        if (empty($phoneNumber)) {
            return false;
        }

        try {
            app('twilio')
                ->lookups
                ->v1
                ->phoneNumbers($phoneNumber)
                ->fetch();

            return true;
        } catch (TwilioException $e) {
            return false;
        }
    }
}
