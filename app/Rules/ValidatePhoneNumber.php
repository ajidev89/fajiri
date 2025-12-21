<?php

namespace App\Rules;

use App\Http\Services\TwilioService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidatePhoneNumber implements ValidationRule
{
    // /**
    //  * Run the validation rule.
    //  *
    //  * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
    //  */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isValid = TwilioService::validate($value);

        if (! $isValid) {
            $fail('The :attribute is not a valid phone number with country code.');
        }
    }
}
