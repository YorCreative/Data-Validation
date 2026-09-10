<?php

namespace YorCreative\DataValidation;

use Closure;
use ReflectionClass;
use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use InvalidArgumentException;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
use YorCreative\DataValidation\Rules\ClosureRule;

class RuleRegistry
{
    private static array $rules = [];
    private static array $closureRules = [];
    private static array $customRuleDirectories = [];
    private static bool $isInitialized = false;

    public static function registerCustomRuleDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Directory '$directory' does not exist.");
        }
        $normalizedDir = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!in_array($normalizedDir, self::$customRuleDirectories)) {
            self::$customRuleDirectories[] = $normalizedDir;
        }
        self::$isInitialized = false;
    }

    public static function clearRegistry(): void
    {
        self::$rules = [];
        self::$closureRules = [];
        self::$customRuleDirectories = [];
        self::$isInitialized = false;
    }

    public static function initializeIfNeeded(): void
    {
        if (!self::$isInitialized) {
            self::loadRules();
        }
    }

    public static function getRule(string $ruleName): ValidationRuleInterface
    {
        self::initializeIfNeeded();
        if (isset(self::$closureRules[$ruleName])) {
            return self::$closureRules[$ruleName];
        }
        if (isset(self::$rules[$ruleName])) {
            return self::$rules[$ruleName];
        }
        throw new InvalidArgumentException(
            "Unknown validation rule: '$ruleName'. Ensure the rule class exists, is discoverable, "
            . "and named correctly (e.g., 'MinRule' for 'min')."
        );
    }

    public static function hasRule(string $ruleName): bool
    {
        self::initializeIfNeeded();
        return isset(self::$closureRules[$ruleName]) || isset(self::$rules[$ruleName]);
    }

    public static function registerClosureRule(string $ruleName, Closure $closure, ?string $defaultMessage = null): void
    {
        self::$closureRules[$ruleName] = new ClosureRule($closure, $defaultMessage);
    }

    private static function loadRules(): void
    {
        self::$rules = [];

        $defaultRulesDir = __DIR__ . DIRECTORY_SEPARATOR . 'Rules';
        self::loadRulesFromDirectory($defaultRulesDir, 'YorCreative\\DataValidation\\Rules');

        foreach (self::$customRuleDirectories as $directory) {
            self::loadRulesFromDirectory($directory, null);
        }

        self::$isInitialized = true;
    }

    private static function loadRulesFromDirectory(string $directory, ?string $baseNamespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $directoryIterator = new RecursiveDirectoryIterator(
            $directory,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS
        );
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = self::getClassNameFromFile($file, $directory, $baseNamespace);
                if ($className && is_readable($file->getRealPath()) && !class_exists($className, false)) {
                    include_once $file->getRealPath();
                }

                if ($className && class_exists($className)) {
                    try {
                        $reflection = new ReflectionClass($className);
                        if (
                            $reflection->implementsInterface(ValidationRuleInterface::class) &&
                            !$reflection->isAbstract() &&
                            !$reflection->isInterface() &&
                            !$reflection->isTrait() &&
                            $className !== 'YorCreative\\DataValidation\\Rules\\ClosureRule'
                        ) {
                            $ruleName = self::deriveRuleNameFromClassName($className);
                            if (!array_key_exists($ruleName, self::$rules)) {
                                self::$rules[$ruleName] = $reflection->newInstance();
                            }
                        }
                    } catch (\Exception $e) {
                        // cannot identity validation rule class
                    }
                    unset($reflection);
                }
                unset($className, $file);
                gc_collect_cycles();
            }
        }
    }

    private static function deriveRuleNameFromClassName(string $className): string
    {
        $shortName = basename(str_replace('\\', '/', $className));
        $ruleNameBase = preg_replace('/Rule$/', '', $shortName);
        $snakeCaseName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $ruleNameBase));
        return $snakeCaseName;
    }

    private static function getClassNameFromFile(
        SplFileInfo $file,
        string $baseDirectory,
        ?string $baseNamespace
    ): ?string {
        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            return null;
        }

        $relativePath = trim(
            substr($realPath, strlen(rtrim($baseDirectory, DIRECTORY_SEPARATOR))),
            DIRECTORY_SEPARATOR
        );
        $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        $classNameWithoutExtension = pathinfo($classPath, PATHINFO_FILENAME);

        $namespaceWithinBase = dirname($classPath);
        $namespaceWithinBase = ($namespaceWithinBase === '.' || $namespaceWithinBase === '')
            ? ''
            : '\\' . $namespaceWithinBase;
        $namespaceWithinBase = str_replace('\\\\', '\\', $namespaceWithinBase);

        if ($baseNamespace) {
            return rtrim($baseNamespace, '\\') . $namespaceWithinBase . '\\' . $classNameWithoutExtension;
        }

        $content = file_get_contents($realPath);
        if ($content === false || !preg_match('/^<\?php\s+namespace\s+([^;]+);/m', $content, $matches)) {
            return null;
        }
        $fileNamespace = trim($matches[1]);
        return $fileNamespace . '\\' . $classNameWithoutExtension;
    }
}
