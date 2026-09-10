<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class RequiredIfRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        $otherField = $parameters[0] ?? '';
        $expectedValues = array_slice($parameters, 1);
        if (empty($otherField) || empty($expectedValues)) {
            return true; // Invalid parameters; don't require the field
        }
        $otherValue = $this->getFieldValue($otherField, $data);
        if (in_array((string)$otherValue, $expectedValues, true)) {
            return !is_null($value) && $value !== '';
        }
        return true; // Other value not matched; field not required
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        if ($customMessage !== null) {
            return $customMessage;
        }
        $otherField = $parameters[0] ?? '';
        if (strpos($otherField, '*') !== false) {
            return 'The :attribute field is required.';
        }
        return 'The :attribute field is required when :other is :values.';
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
        if (count($parameters) < 2) {
            throw new InvalidArgumentException(
                "The 'required_if' rule for field '{$field}' requires at least two parameters."
            );
        }
    }
}
