<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class MaxRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        $max = (float)$parameters[0];
        if (is_numeric($value)) {
            return $value <= $max;
        }
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute may not be greater than :max.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1 || !is_numeric($parameters[0])) {
            throw new InvalidArgumentException(
                "The 'max' rule for field '{$field}' requires exactly one numeric parameter."
            );
        }
    }
}
