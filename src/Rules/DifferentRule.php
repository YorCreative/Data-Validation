<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class DifferentRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        $otherField = $parameters[0] ?? '';
        if ($otherField === '') {
            return false;
        }
        $otherValue = $this->getFieldValue($otherField, $data);
        return $value !== $otherValue;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute must be different from :other.';
    }

    private function getFieldValue(string $field, array $data)
    {
        $keys = explode('.', $field);
        $value = $data;
        foreach ($keys as $key) {
            if ($key === '*' || !is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1) {
            throw new InvalidArgumentException(
                "The 'different' rule for field '{$field}' requires exactly one parameter."
            );
        }
    }
}
