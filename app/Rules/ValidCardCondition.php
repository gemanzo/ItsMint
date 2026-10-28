<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CardCondition;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a recognized card condition: a code ("NM"), a label
 * ("Near Mint") or a CardTrader name ("Slightly Played").
 * Normalize it afterwards with CardCondition::normalize().
 */
final class ValidCardCondition implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || CardCondition::fromProviderValue($value) === null) {
            $fail('The :attribute must be a valid card condition (NM, LP, MP, HP or DMG).');
        }
    }
}
