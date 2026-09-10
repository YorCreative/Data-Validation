<?php

namespace YorCreative\DataValidation\Tests\Unit;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SplFileInfo;
use YorCreative\DataValidation\RuleRegistry;
use YorCreative\DataValidation\Rules\ClosureRule;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;

class RuleRegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rule_registry_test_' . uniqid();
        mkdir($this->tempDir);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $rulesProp = $reflection->getProperty('rules');
        $rulesProp->setValue(null, []);

        $closureRulesProp = $reflection->getProperty('closureRules');
        $closureRulesProp->setValue(null, []);

        $dirsProp = $reflection->getProperty('customRuleDirectories');
        $dirsProp->setValue(null, []);

        $initProp = $reflection->getProperty('isInitialized');
        $initProp->setValue(null, false);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        gc_collect_cycles();
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    public function testRegisterCustomRuleDirectoryWithValidDirectory(): void
    {
        RuleRegistry::registerCustomRuleDirectory($this->tempDir);
        $reflection = new ReflectionClass(RuleRegistry::class);
        $dirsProp = $reflection->getProperty('customRuleDirectories');
        $dirs = $dirsProp->getValue();
        $this->assertContains(rtrim($this->tempDir, DIRECTORY_SEPARATOR), $dirs);
        $initProp = $reflection->getProperty('isInitialized');
        $this->assertFalse($initProp->getValue());
    }

    public function testRegisterCustomRuleDirectoryWithInvalidDirectoryThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Directory '/non/existent/path' does not exist.");
        RuleRegistry::registerCustomRuleDirectory('/non/existent/path');
    }

    public function testRegisterCustomRuleDirectorySkipsDuplicates(): void
    {
        RuleRegistry::registerCustomRuleDirectory($this->tempDir);
        RuleRegistry::registerCustomRuleDirectory($this->tempDir);
        $reflection = new ReflectionClass(RuleRegistry::class);
        $dirsProp = $reflection->getProperty('customRuleDirectories');
        $dirs = $dirsProp->getValue();
        $this->assertCount(1, $dirs);
        $this->assertContains(rtrim($this->tempDir, DIRECTORY_SEPARATOR), $dirs);
    }

    public function testInitializeIfNeededCallsLoadRulesWhenNotInitialized(): void
    {
        $reflection = new ReflectionClass(RuleRegistry::class);
        $initProp = $reflection->getProperty('isInitialized');
        $initProp->setValue(null, false);

        $rulesProp = $reflection->getProperty('rules');
        $initialRules = $rulesProp->getValue();

        RuleRegistry::hasRule('required'); // Triggers initialization

        $this->assertTrue($initProp->getValue());
        $this->assertNotSame($initialRules, $rulesProp->getValue());
        $rules = $rulesProp->getValue();
        $this->assertArrayHasKey('required', $rules);
        $this->assertInstanceOf(ValidationRuleInterface::class, $rules['required']);
    }

    public function testInitializeIfNeededSkipsLoadRulesWhenInitialized(): void
    {
        $reflection = new ReflectionClass(RuleRegistry::class);
        $initProp = $reflection->getProperty('isInitialized');
        $initProp->setValue(null, true);

        $rulesProp = $reflection->getProperty('rules');
        $initialRules = $rulesProp->getValue();

        RuleRegistry::hasRule('required'); // Should not reload rules

        $this->assertSame($initialRules, $rulesProp->getValue());
    }

    public function testGetRuleReturnsClosureRule(): void
    {
        $closure = fn ($field, $value, Closure $fail) => true;
        RuleRegistry::registerClosureRule('custom', $closure, 'Custom error.');
        $rule = RuleRegistry::getRule('custom');
        $this->assertInstanceOf(ClosureRule::class, $rule);
    }

    public function testGetRuleReturnsRegisteredRule(): void
    {
        $rule = RuleRegistry::getRule('required');
        $this->assertInstanceOf(ValidationRuleInterface::class, $rule);
        $this->assertInstanceOf('YorCreative\\DataValidation\\Rules\\RequiredRule', $rule);
    }

    public function testGetRuleThrowsExceptionForUnknownRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown validation rule: 'unknown'.");
        RuleRegistry::getRule('unknown');
    }

    public function testHasRuleReturnsTrueForClosureRule(): void
    {
        RuleRegistry::registerClosureRule('custom', fn ($field, $value, Closure $fail) => true);
        $this->assertTrue(RuleRegistry::hasRule('custom'));
    }

    public function testHasRuleReturnsTrueForRegisteredRule(): void
    {
        $this->assertTrue(RuleRegistry::hasRule('required'));
    }

    public function testHasRuleReturnsFalseForUnknownRule(): void
    {
        $this->assertFalse(RuleRegistry::hasRule('unknown'));
    }

    public function testRegisterClosureRuleAddsRule(): void
    {
        $closure = fn ($field, $value, Closure $fail) => true;
        RuleRegistry::registerClosureRule('custom', $closure, 'Custom error.');
        $reflection = new ReflectionClass(RuleRegistry::class);
        $closureRulesProp = $reflection->getProperty('closureRules');
        $closureRules = $closureRulesProp->getValue();
        $this->assertArrayHasKey('custom', $closureRules);
        $this->assertInstanceOf(ClosureRule::class, $closureRules['custom']);
    }

    public function testLoadRulesClearsRulesAndLoadsFromDirectories(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'TestRule.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
class TestRule implements ValidationRuleInterface {
    public function validate(string $field, $value, array $parameters, array $data): bool { return true; }
    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string { return ''; }
    public function validateParameters(string $field, array $parameters): void { }
}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $rulesProp = $reflection->getProperty('rules');

        RuleRegistry::hasRule('required'); // Initialize default rules
        $initialRules = $rulesProp->getValue();
        $this->assertNotEmpty($initialRules);

        RuleRegistry::registerCustomRuleDirectory($this->tempDir);
        $initProp = $reflection->getProperty('isInitialized');
        $initProp->setValue(null, false);

        RuleRegistry::hasRule('test'); // Trigger reload with custom rules

        $rules = $rulesProp->getValue();
        $this->assertArrayHasKey('test', $rules);
        $this->assertInstanceOf(ValidationRuleInterface::class, $rules['test']);
        $this->assertArrayHasKey('required', $rules);
        $this->assertTrue($initProp->getValue());
    }

    public function testLoadRulesFromInvalidDirectorySkips(): void
    {
        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, '/non/existent/path', null);

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testLoadRulesFromDirectoryLoadsValidRule(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'TestRule.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
class TestRule implements ValidationRuleInterface {
    public function validate(string $field, $value, array $parameters, array $data): bool { return true; }
    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string { return ''; }
    public function validateParameters(string $field, array $parameters): void { }
}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, 'TestRules');

        $rulesProp = $reflection->getProperty('rules');
        $rules = $rulesProp->getValue();
        $this->assertArrayHasKey('test', $rules);
        $this->assertInstanceOf(ValidationRuleInterface::class, $rules['test']);
    }

    public function testLoadRulesSkipsNonPhpFiles(): void
    {
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'test.txt', 'Not a PHP file');

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, null);

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testLoadRulesSkipsAbstractClasses(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'AbstractRule.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
abstract class AbstractRule implements ValidationRuleInterface {
    public function validate(string $field, $value, array $parameters, array $data): bool { return true; }
    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string { return ''; }
    public function validateParameters(string $field, array $parameters): void { }
}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, 'TestRules');

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testLoadRulesSkipsInterfaces(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'TestInterface.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
interface TestInterface extends ValidationRuleInterface {}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, 'TestRules');

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testLoadRulesSkipsTraits(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'TestTrait.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
trait TestTrait {
    public function validate(string $field, $value, array $parameters, array $data): bool { return true; }
}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, 'TestRules');

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testLoadRulesSkipsClosureRule(): void
    {
        $reflection = new ReflectionClass(RuleRegistry::class);
        $rulesProp = $reflection->getProperty('rules');
        $rulesProp->setValue(null, ['closure' => new ClosureRule(fn () => true)]);

        $loadRulesMethod = $reflection->getMethod('loadRules');
        $loadRulesMethod->invoke(null);

        $rules = $rulesProp->getValue();
        $this->assertNotContains(ClosureRule::class, array_map('get_class', $rules));
        $this->assertArrayHasKey('required', $rules);
    }

    public function testLoadRulesHandlesReflectionException(): void
    {
        $ruleFile = $this->tempDir . DIRECTORY_SEPARATOR . 'BrokenRule.php';
        $ruleContent = <<<'PHP'
<?php
namespace TestRules;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
class BrokenRule implements ValidationRuleInterface {
    public function __construct() { throw new \Exception('Constructor failure'); }
    public function validate(string $field, $value, array $parameters, array $data): bool { return true; }
    public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string { return ''; }
    public function validateParameters(string $field, array $parameters): void { }
}
PHP;
        file_put_contents($ruleFile, $ruleContent);

        $reflection = new ReflectionClass(RuleRegistry::class);
        $loadRulesFromDirectoryMethod = $reflection->getMethod('loadRulesFromDirectory');
        $loadRulesFromDirectoryMethod->invoke(null, $this->tempDir, 'TestRules');

        $rulesProp = $reflection->getProperty('rules');
        $this->assertEmpty($rulesProp->getValue());
    }

    public function testDeriveRuleNameFromClassName(): void
    {
        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('deriveRuleNameFromClassName');

        $this->assertEquals('min', $method->invoke(null, 'YorCreative\\DataValidation\\Rules\\MinRule'));
        $this->assertEquals('required_if', $method->invoke(null, 'RequiredIfRule'));
        $this->assertEquals('test', $method->invoke(null, 'Test\\Namespace\\TestRule'));
    }

    public function testGetClassNameFromFileWithBaseNamespace(): void
    {
        $file = new SplFileInfo($this->tempDir . DIRECTORY_SEPARATOR . 'TestRule.php');
        file_put_contents($file->getPathname(), '<?php namespace TestRules; class TestRule {}');

        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('getClassNameFromFile');

        $className = $method->invoke(null, $file, $this->tempDir, 'TestRules');
        $this->assertEquals('TestRules\\TestRule', $className);
    }

    public function testGetClassNameFromFileWithoutBaseNamespace(): void
    {
        $file = new SplFileInfo($this->tempDir . DIRECTORY_SEPARATOR . 'TestRule.php');
        file_put_contents($file->getPathname(), '<?php namespace CustomRules; class TestRule {}');

        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('getClassNameFromFile');

        $className = $method->invoke(null, $file, $this->tempDir, null);
        $this->assertEquals('CustomRules\\TestRule', $className);
    }

    public function testGetClassNameFromFileWithNoNamespace(): void
    {
        $file = new SplFileInfo($this->tempDir . DIRECTORY_SEPARATOR . 'TestRule.php');
        file_put_contents($file->getPathname(), '<?php class TestRule {}');

        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('getClassNameFromFile');

        $className = $method->invoke(null, $file, $this->tempDir, null);
        $this->assertNull($className);
    }

    public function testGetClassNameFromFileWithNonExistentFile(): void
    {
        $nonExistentFile = new SplFileInfo($this->tempDir . DIRECTORY_SEPARATOR . 'NonExistentRule.php');
        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('getClassNameFromFile');

        $className = $method->invoke(null, $nonExistentFile, $this->tempDir, null);
        $this->assertNull($className);
    }

    public function testGetClassNameFromFileWithNonRealPath(): void
    {
        $nonRealFile = new SplFileInfo($this->tempDir . DIRECTORY_SEPARATOR . 'NonRealFile.php');
        if (file_exists($nonRealFile->getPathname())) {
            unlink($nonRealFile->getPathname());
        }

        $reflection = new ReflectionClass(RuleRegistry::class);
        $method = $reflection->getMethod('getClassNameFromFile');

        $className = $method->invoke(null, $nonRealFile, $this->tempDir, 'TestRules');
        $this->assertNull($className);
    }
}
