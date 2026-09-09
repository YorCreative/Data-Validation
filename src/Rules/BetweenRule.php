<?php

namespace YorCreative\DataValidation\Rules;

use InvalidArgumentException;

class BetweenRule implements ValidationRuleInterface
{
    public function validate(string $field, $value, array $parameters, array $data): bool
    {
        $min = $parameters[0] ?? 0;
        $max = $parameters[1] ?? 0;
        if (is_numeric($value)) {
            $v = (float)$value;
            return $v >= (float)$min && $v <= (float)$max;
        }
        if (is_string($value)) {
            $l = mb_strlen((string)$value);
            return $l >= (float)$min && $l <= (float)$max;
        }
        if (is_array($value)) {
            $c = count($value);
            return $c >= (float)$min && $c <= (float)$max;
        }
        return false;
    }

    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
    {
        $template = $customMessage ?? 'The :attribute must be between :min and :max.';
        $message = str_replace(':attribute', $field, $template);
        if (!empty($parameters)) {
            $message = str_replace(':min', $parameters[0], $message);
            $message = str_replace(':max', $parameters[1], $message);
        }
        return $message;
    }

    public function validateParameters(string $field, array $parameters): void
    {
        if (count($parameters) !== 2 || !is_numeric($parameters[0]) || !is_numeric($parameters[1])) {
            throw new InvalidArgumentException(
                "The 'between' rule for field '{$field}' requires exactly two numeric parameters."
            );
        }
    }
}
