<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class StartsWithRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }
        foreach ($parameters as $prefix) {
            $prefixAsString = (string)$prefix;
            if ($prefixAsString === '') {
                continue;
            }
            if (str_starts_with($value, $prefixAsString)) {
                return true;
            }
        }
        return false;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must start with one of: :values.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException(
                "The 'starts_with' rule for field '{$field}' requires at least one parameter."
            );
        }
    }
}
