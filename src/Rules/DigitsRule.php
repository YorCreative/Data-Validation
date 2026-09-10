<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class DigitsRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }
        $valueAsString = (string)$value;
        return ctype_digit($valueAsString) && strlen($valueAsString) === (int)$parameters[0];
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must be :digits digits.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1 || !is_numeric($parameters[0])) {
            throw new InvalidArgumentException(
                "The 'digits' rule for field '{$field}' requires exactly one numeric parameter."
            );
        }
    }
}
