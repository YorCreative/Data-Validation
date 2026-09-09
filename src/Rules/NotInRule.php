<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class NotInRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        return !in_array($value, $parameters, true);
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must not be one of: :values.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (empty($parameters)) {
            throw new InvalidArgumentException(
                "The 'not_in' rule for field '{$field}' requires at least one parameter."
            );
        }
    }
}
