<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class MinRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        $min = (float)$parameters[0];
        if (is_numeric($value)) {
            return $value >= $min;
        }
        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must be at least :min.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1 || !is_numeric($parameters[0])) {
            throw new InvalidArgumentException(
                "The 'min' rule for field '{$field}' requires exactly one numeric parameter."
            );
        }
    }
}
