<?php

namespace YorCreative\DataValidation\Rules;

use DateTime;
use InvalidArgumentException;

class DateFormatRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        if (!is_string($value) || empty($parameters[0])) {
            return false;
        }

        $format = $parameters[0];
        $date = DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        return $customMessage ?? 'The :attribute does not match the format :format.';
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 1) {
            throw new InvalidArgumentException(
                "The 'date_format' rule for field '{$field}' requires exactly one parameter."
            );
        }
    }
}
