<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class EndsWithRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        foreach ($parameters as $suffix) {
            $suffixAsString = (string)$suffix;
            if ($suffixAsString === '') {
                continue;
            }
            if (str_ends_with($value, $suffixAsString)) {
                return true;
            }
        }
        return false;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must end with one of: :values.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException(
                "The 'ends_with' rule for field '{$field}' requires at least one parameter."
            );
        }
    }
}
