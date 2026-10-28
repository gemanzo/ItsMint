<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CardLanguage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a supported card language, in any accepted spelling
 * ("en", "EN", "ja", "jp", ...). Normalize it afterwards with CardLanguage::normalize().
 */
final class ValidCardLanguage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || CardLanguage::fromProviderValue($value) === null) {
            $fail('The :attribute must be a supported card language.');
        }
    }
}
