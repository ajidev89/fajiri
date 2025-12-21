<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;

class ValidateToken implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $data = Crypt::decrypt($value);

        if(!$data['value'] || !$data['expiry']){
            $fail('The :attribute is not a valid token.');
        }   

        if(now()->timestamp > $data['expiry']) {
            $fail('The :attribute has expired.');
        }   
    }
}
