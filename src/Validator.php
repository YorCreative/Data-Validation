<?php

namespace YorCreative\DataValidation;

use Closure;
use InvalidArgumentException;
use YorCreative\DataValidation\Rules\ClosureRule;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $messages = [];
    private array $attributes = [];
    private array $fieldValueCache = [];
    private bool $stopOnFirstError;
    private bool $hasValidated = false;
    private int $fieldCacheLimit = 1000;
    private static array $rulesNeedingParamPrep = ['same', 'different', 'required_if', 'required_unless'];
    private static array $parsedRulesCache = [];
    private static int $cacheLimit = 500;
    private const int CHUNK_SIZE_DEFAULT = 500;
    private const int CHUNK_SIZE_MID = 1000;
    private const int DATA_SIZE_THRESHOLD_LOWER = 1000;
    private const int DATA_SIZE_THRESHOLD_UPPER = 3000;

    private ?DataValidationConfig $config = null;

    public function __construct(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = [],
        bool $stopOnFirstError = false,
        ?DataValidationConfig $config = null
    ) {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->attributes = $attributes;
        $this->stopOnFirstError = $stopOnFirstError;
        $this->config = $config;
        if ($config) {
            $this->fieldCacheLimit = $config->fieldCacheLimit;
            self::$cacheLimit = $config->parsedRulesCache;
        }
        $this->validateRulesConfiguration();
    }

    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = [],
        bool $stopOnFirstError = false,
        ?DataValidationConfig $config = null
    ): self {
        return new self($data, $rules, $messages, $attributes, $stopOnFirstError, $config);
    }

    public static function isValid(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = [],
        bool $stopOnFirstError = false,
        ?DataValidationConfig $config = null
    ): bool {
        return static::make($data, $rules, $messages, $attributes, $stopOnFirstError, $config)->validate();
    }

    public function clearParsedRulesCache(): void
    {
        self::$parsedRulesCache = [];
    }

    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? array_map('trim', explode(',', $parts[1])) : [];
        return [$ruleName, $parameters];
    }

    private function validateRulesConfiguration(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            if (!is_string($ruleSet) && !is_array($ruleSet)) {
                throw new InvalidArgumentException("Invalid rule set type for field '{$field}': must be string or array");
            }
            $parsedRules = $this->getParsedRules($ruleSet);
            foreach ($parsedRules as $rule) {
                if (is_string($rule)) {
                    [$ruleName, $parameters] = $this->parseRule($rule);
                    if (!RuleRegistry::hasRule($ruleName)) {
                        throw new InvalidArgumentException("Unknown validation rule '{$ruleName}' for field '{$field}'");
                    }
                    $ruleInstance = RuleRegistry::getRule($ruleName);
                    $ruleInstance->validateParameters($field, $parameters);
                } elseif (!($rule instanceof Closure)) {
                    throw new InvalidArgumentException("Invalid rule type for field '{$field}': must be string or Closure");
                }
            }
        }
    }

    public function validate(): bool
    {
        $this->hasValidated = true;
        $this->errors = [];
        $this->fieldValueCache = [];
        $stack = [];

        foreach ($this->rules as $field => $ruleSet) {
            if (empty($ruleSet) && !($ruleSet instanceof Closure)) {
                continue;
            }
            $stack[] = [
                'data' => &$this->data,
                'fieldParts' => explode('.', $field),
                'rules' => $this->getParsedRules($ruleSet),
                'currentPath' => '',
                'fullPath' => $field
            ];
        }

        while (!empty($stack)) {
            $current = array_shift($stack);
            $dataValue = &$current['data'];
            $fieldParts = $current['fieldParts'];
            $rules = $current['rules'];
            $currentPath = $current['currentPath'];
            $fullPath = $current['fullPath'];

            if (empty($fieldParts)) {
                $this->applyRules($currentPath, $dataValue, $rules, $fullPath, $this->data);
            } elseif ($fieldParts[0] === '*') {
                if (!is_array($dataValue)) {
                    if ($this->hasRequiredRule($rules)) {
                        $this->handleNonArray($currentPath, $fullPath, $rules);
                    }
                    continue;
                }
                $chunkSize = $this->determineChunkSize(count($dataValue));
                $keys = array_keys($dataValue);
                foreach (array_chunk($keys, $chunkSize) as $chunkKeys) {
                    foreach ($chunkKeys as $key) {
                        $newPath = $currentPath ? "$currentPath.$key" : (string)$key;
                        $newFullPath = preg_replace('/(?<!\\\\)\*/', (string)$key, $fullPath, 1);
                        $stack[] = [
                            'data' => &$dataValue[$key],
                            'fieldParts' => array_slice($fieldParts, 1),
                            'rules' => $rules,
                            'currentPath' => $newPath,
                            'fullPath' => $newFullPath
                        ];
                    }
                    gc_collect_cycles();
                }
            } else {
                $part = array_shift($fieldParts);
                $subData = is_array($dataValue) && array_key_exists($part, $dataValue) ? $dataValue[$part] : null;
                $newPath = $currentPath ? "$currentPath.$part" : $part;
                $stack[] = [
                    'data' => &$subData,
                    'fieldParts' => $fieldParts,
                    'rules' => $rules,
                    'currentPath' => $newPath,
                    'fullPath' => $fullPath
                ];
            }
            if ($this->stopOnFirstError && !empty($this->errors)) {
                return false;
            }
        }
        return empty($this->errors);
    }

    private function hasRequiredRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'required')) {
                return true;
            }
        }
        return false;
    }

    private function determineChunkSize(int $dataSize): int
    {
        if (($chunkSize = $this->config?->chunkSize) !== null) {
            return max(1, $chunkSize);
        }

        if ($dataSize <= self::DATA_SIZE_THRESHOLD_LOWER) {
            return max(self::CHUNK_SIZE_DEFAULT, $dataSize);
        }

        if ($dataSize <= self::DATA_SIZE_THRESHOLD_UPPER) {
            return self::CHUNK_SIZE_MID;
        }

        return self::CHUNK_SIZE_DEFAULT;
    }


    private function handleNonArray(string $currentPath, string $fullPath, array $rules): void
    {
        if ($currentPath !== '') {
            $this->addErrorMessageByRuleName($currentPath, 'array', []);
        }
    }

    private function getParsedRules($ruleSet): array
    {
        if ($ruleSet instanceof Closure) {
            return [$ruleSet];
        }
        if (is_string($ruleSet)) {
            if ($ruleSet === '') {
                return [];
            }
            if (!isset(self::$parsedRulesCache[$ruleSet])) {
                if (count(self::$parsedRulesCache) >= self::$cacheLimit) {
                    $itemsToRetain = max(1, intdiv(self::$cacheLimit, 2));
                    self::$parsedRulesCache = array_slice(self::$parsedRulesCache, -$itemsToRetain, null, true);
                }
                self::$parsedRulesCache[$ruleSet] = explode('|', $ruleSet);
            }
            return self::$parsedRulesCache[$ruleSet];
        }
        return is_array($ruleSet) ? $ruleSet : [];
    }

    private function applyRules(string $field, $value, array $rules, string $originalRulePath, array $data): void
    {
        $isNullable = in_array('nullable', $rules, true);
        if (($value === null || (is_string($value) && $value === '')) && $isNullable) {
            return;
        }

        foreach ($rules as $rule) {
            if ($this->stopOnFirstError && isset($this->errors[$field]) && !empty($this->errors[$field])) {
                return;
            }

            $ruleInstance = null;
            $ruleName = null;
            $originalParams = [];
            $processedParams = [];

            if ($rule instanceof Closure) {
                $ruleInstance = new ClosureRule($rule);
                $ruleName = 'closure_validation';
            } elseif (is_string($rule)) {
                [$parsedRuleName, $parsedParams] = $this->parseRule($rule);
                $ruleName = $parsedRuleName;
                $originalParams = $parsedParams;
                $processedParams = $parsedParams;
                try {
                    $ruleInstance = RuleRegistry::getRule($ruleName);
                } catch (InvalidArgumentException $e) {
                    $this->addErrorRaw($field, "Validation rule '{$ruleName}' is not defined.");
                    continue;
                }
                if (in_array($ruleName, self::$rulesNeedingParamPrep) && isset($originalParams[0])) {
                    $otherPath = $this->resolveOtherFieldPathForComparison($originalParams[0], $field);
                    if (in_array($ruleName, ['required_if', 'required_unless'])) {
                        $processedParams = array_merge([$otherPath], array_slice($originalParams, 1));
                    } else {
                        $otherValue = $this->getFieldValue($otherPath);
                        $processedParams = [$originalParams[0], $otherValue];
                    }
                }
            } else {
                continue;
            }

            if ($ruleInstance && !$ruleInstance->validate($field, $value, $processedParams, $data)) {
                $customTpl = $this->messages[$field . '.' . $ruleName] ?? $this->messages[$ruleName] ?? null;
                $baseTpl = $ruleInstance->getErrorMessage($field, $originalParams, $customTpl);
                if (!empty($baseTpl)) {
                    $this->addErrorRaw($field, $this->formatMessage($baseTpl, $field, $ruleName, $originalParams));
                }
            }
        }
    }

    private function resolveOtherFieldPathForComparison(string $otherFieldWithPossibleWildcards, string $currentFieldPath): string
    {
        $otherParts = explode('.', $otherFieldWithPossibleWildcards);
        $currentParts = explode('.', $currentFieldPath);

        $resolvedParts = [];
        for ($i = 0; $i < count($otherParts); $i++) {
            if ($otherParts[$i] === '*') {
                if ($i < count($currentParts)) {
                    $resolvedParts[] = $currentParts[$i];
                } else {
                    $resolvedParts[] = '*';
                }
            } else {
                $resolvedParts[] = $otherParts[$i];
            }
        }
        return implode('.', $resolvedParts);
    }

    private function formatMessage(string $template, string $field, string $ruleName, array $ruleParameters): string
    {
        $fieldName = $this->getAttributeName($field);
        $message = str_replace([':attribute', ':field'], $fieldName, $template);

        $replacements = [];

        if (in_array($ruleName, ['in', 'not_in', 'starts_with', 'ends_with', 'required_if', 'required_unless']) && !empty($ruleParameters)) {
            if (in_array($ruleName, ['required_if', 'required_unless'])) {
                $values = array_map('strval', array_slice($ruleParameters, 1));
            } else {
                $values = array_map('strval', $ruleParameters);
            }
            $replacements[':values'] = implode(', ', $values);
        }

        if (isset($ruleParameters[0])) {
            $replacements[':min'] = (string)$ruleParameters[0];
            $replacements[':max'] = (string)$ruleParameters[0];
            $replacements[':digits'] = (string)$ruleParameters[0];
            $replacements[':value'] = (string)$ruleParameters[0];
            $replacements[':format'] = (string)$ruleParameters[0];
        }
        if (isset($ruleParameters[1]) && $ruleName === 'between') {
            $replacements[':max'] = (string)$ruleParameters[1];
        }
        if (in_array($ruleName, ['same', 'different', 'required_if', 'required_unless']) && isset($ruleParameters[0])) {
            $resolvedOtherPath = $this->resolveOtherFieldPathForComparison($ruleParameters[0], $field);
            $replacements[':other'] = $this->getAttributeName($resolvedOtherPath);
            $replacements[':other_raw_name'] = $resolvedOtherPath;
            if (in_array($ruleName, ['required_if', 'required_unless']) && count($ruleParameters) >= 2) {
                $replacements[':value'] = $ruleParameters[1];
                $replacements[':values'] = implode(', ', array_slice($ruleParameters, 1));
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    private function addErrorMessageByRuleName(string $field, string $ruleName, array $parameters): void
    {
        $customTpl = $this->messages[$field . '.' . $ruleName] ?? $this->messages[$ruleName] ?? null;
        try {
            $ruleInstance = RuleRegistry::getRule($ruleName);
            $baseTpl = $ruleInstance->getErrorMessage($field, $parameters, $customTpl);
            $this->addErrorRaw($field, $this->formatMessage($baseTpl, $field, $ruleName, $parameters));
        } catch (InvalidArgumentException $e) {
            if ($ruleName === 'array') {
                $template = 'The :attribute must be an array.';
                $this->addErrorRaw($field, $this->formatMessage($template, $field, $ruleName, $parameters));
            } else {
                $this->addErrorRaw($field, "The {$this->getAttributeName($field)} is invalid (unknown rule {$ruleName}).");
            }
        }
    }

    public function getFieldValue(string $field)
    {
        if (array_key_exists($field, $this->fieldValueCache)) {
            return $this->fieldValueCache[$field];
        }
        if (count($this->fieldValueCache) >= $this->fieldCacheLimit) {
            array_shift($this->fieldValueCache);
        }
        $keys = explode('.', $field);
        $value = $this->data;
        foreach ($keys as $key) {
            if ($key === '*' || !is_array($value) || !array_key_exists($key, $value)) {
                return $this->fieldValueCache[$field] = null;
            }
            $value = $value[$key];
        }
        return $this->fieldValueCache[$field] = $value;
    }

    private function addErrorRaw(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function getAttributeName(string $field): string
    {
        if (isset($this->attributes[$field])) {
            return $this->attributes[$field];
        }
        $prettyField = $field;
        $prettyField = preg_replace('/\.(\d+)\./', ' $1 ', $prettyField);
        $prettyField = preg_replace('/\.(\d+)$/', ' $1', $prettyField);
        $prettyField = preg_replace('/^(\d+)\./', '$1 ', $prettyField);
        return str_replace(['.', '_'], ' ', $prettyField);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !$this->validationPassed();
    }

    public function passes(): bool
    {
        return $this->validationPassed();
    }

    private function validationPassed(): bool
    {
        if (!$this->hasValidated) {
            return $this->validate();
        }

        return empty($this->errors);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'rules' => $this->rules,
            'errors' => $this->errors,
            'messages' => $this->messages,
            'attributes' => $this->attributes,
            'stopOnFirstError' => $this->stopOnFirstError,
            'hasValidated' => $this->hasValidated
        ];
    }

    public function __unserialize(array $sData): void
    {
        $this->data = $sData['data'] ?? [];
        $this->rules = $sData['rules'] ?? [];
        $this->errors = $sData['errors'] ?? [];
        $this->messages = $sData['messages'] ?? [];
        $this->attributes = $sData['attributes'] ?? [];
        $this->stopOnFirstError = $sData['stopOnFirstError'] ?? false;
        $this->hasValidated = $sData['hasValidated'] ?? false;
        $this->fieldValueCache = [];
    }
}
