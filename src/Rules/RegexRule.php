<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class RegexRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        if (!is_string($value) || empty($parameters[0])) {
            return false;
        }
        return @preg_match($parameters[0], $value) === 1;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute format is invalid.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1) {
            throw new InvalidArgumentException(
                "The 'regex' rule for field '{$field}' requires exactly one parameter (the pattern)."
            );
        }
        $pattern = $parameters[0];
        if (!is_string($pattern)) {
            throw new InvalidArgumentException(
                "The 'regex' rule for field '{$field}' requires a string pattern as its parameter."
            );
        }
        // Test the pattern for validity
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException("The 'regex' pattern for field '{$field}' is invalid.");
        }
    }
}
