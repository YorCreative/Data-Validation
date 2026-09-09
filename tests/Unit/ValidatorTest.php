<?php

namespace YorCreative\DataValidation\Tests\Unit;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use YorCreative\DataValidation\DataValidationConfig;
use YorCreative\DataValidation\RuleRegistry;
use YorCreative\DataValidation\Rules\ValidationRuleInterface;
use YorCreative\DataValidation\Validator;

class ValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset RuleRegistry to ensure it loads actual rules
        $reflection = new ReflectionClass(RuleRegistry::class);
        $rulesProp = $reflection->getProperty('rules');
        $rulesProp->setValue(null, []);

        $closureRulesProp = $reflection->getProperty('closureRules');
        $closureRulesProp->setValue(null, []);

        $dirsProp = $reflection->getProperty('customRuleDirectories');
        $dirsProp->setValue(null, []);

        $initProp = $reflection->getProperty('isInitialized');
        $initProp->setValue(null, false);

        // Validator keeps the parsed-rules cache AND its limit in statics.
        // A test that lowers the limit would otherwise leak it into every
        // test that follows in this file.
        $validatorReflection = new ReflectionClass(Validator::class);
        $validatorReflection->getProperty('parsedRulesCache')->setValue(null, []);
        $validatorReflection->getProperty('cacheLimit')->setValue(null, 500);
    }

    protected function tearDown(): void
    {
        gc_collect_cycles();
        parent::tearDown();
    }

    public function testApplyRulesWithSameRule(): void
    {
        $data = ['field' => 'value', 'other' => 'value'];
        $rules = ['field' => 'same:other'];
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->validate(), 'Validation should pass for same rule');
        $this->assertEmpty($validator->errors(), 'No errors should be present');
    }

    public function testAddErrorMessageByRuleNameWithUnknownRule(): void
    {
        $validator = Validator::make(['field' => 'value'], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('addErrorMessageByRuleName');
        $method->invoke($validator, 'field', 'unknown', []);
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field is invalid (unknown rule unknown)', $validator->errors()['field'][0]);
    }

    public function testAddErrorMessageByRuleNameWithKnownRule(): void
    {
        $data = ['field' => 5];
        $rules = ['field' => 'min:10'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field must be at least 10.', $validator->errors()['field'][0]);
    }

    public function testValidateRulesConfigurationWithInvalidRuleSetType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid rule set type for field 'field': must be string or array");
        Validator::make([], ['field' => new \stdClass()]);
    }

    public function testValidateRulesConfigurationWithUnknownRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown validation rule 'unknown' for field 'field'");
        Validator::make([], ['field' => 'unknown']);
    }

    public function testValidateRulesConfigurationWithInvalidMinParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'min' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'min:abc']);
    }

    public function testValidateRulesConfigurationWithMissingMinParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'min' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'min']);
    }

    public function testValidateRulesConfigurationWithInvalidMaxParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'max' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'max:abc']);
    }

    public function testValidateRulesConfigurationWithMissingMaxParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'max' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'max']);
    }

    public function testValidateRulesConfigurationWithInvalidDigitsParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'digits' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'digits:abc']);
    }

    public function testValidateRulesConfigurationWithMissingDigitsParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'digits' rule for field 'field' requires exactly one numeric parameter.");
        Validator::make([], ['field' => 'digits']);
    }

    public function testValidateRulesConfigurationWithInvalidBetweenParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'between' rule for field 'field' requires exactly two numeric parameters.");
        Validator::make([], ['field' => 'between:abc,def']);
    }

    public function testValidateRulesConfigurationWithMissingBetweenParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'between' rule for field 'field' requires exactly two numeric parameters.");
        Validator::make([], ['field' => 'between:10']);
    }

    public function testValidateRulesConfigurationWithMissingInParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'in' rule for field 'field' requires at least one parameter.");
        Validator::make([], ['field' => 'in']);
    }

    public function testValidateRulesConfigurationWithMissingNotInParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'not_in' rule for field 'field' requires at least one parameter.");
        Validator::make([], ['field' => 'not_in']);
    }

    public function testValidateRulesConfigurationWithMissingStartsWithParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'starts_with' rule for field 'field' requires at least one parameter.");
        Validator::make([], ['field' => 'starts_with']);
    }

    public function testValidateRulesConfigurationWithMissingEndsWithParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'ends_with' rule for field 'field' requires at least one parameter.");
        Validator::make([], ['field' => 'ends_with']);
    }

    public function testValidateRulesConfigurationWithInvalidSameParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'same' rule for field 'field' requires exactly one parameter.");
        Validator::make([], ['field' => 'same']);
    }

    public function testValidateRulesConfigurationWithInvalidDifferentParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'different' rule for field 'field' requires exactly one parameter.");
        Validator::make([], ['field' => 'different']);
    }

    public function testValidateRulesConfigurationWithInvalidDateFormatParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'date_format' rule for field 'field' requires exactly one parameter.");
        Validator::make([], ['field' => 'date_format']);
    }

    public function testValidateRulesConfigurationWithInvalidRequiredIfParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'required_if' rule for field 'field' requires at least two parameters.");
        Validator::make([], ['field' => 'required_if:status']);
    }

    public function testValidateRulesConfigurationWithInvalidRequiredUnlessParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'required_unless' rule for field 'field' requires at least two parameters.");
        Validator::make([], ['field' => 'required_unless:status']);
    }

    public function testValidateRulesConfigurationWithClosureRule(): void
    {
        $rules = [
            'field' => [function ($field, $value, $fail) {
                if (empty($value)) {
                    $fail("The {$field} must not be empty.");
                }
            }]
        ];
        $validator = Validator::make([], $rules);
        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testValidateRulesConfigurationWithValidRules(): void
    {
        $rules = ['field' => 'required|min:5'];
        $validator = Validator::make([], $rules);
        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testParseRulesWithEmptyString(): void
    {
        $validator = Validator::make([], ['field' => '']);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('getParsedRules');
        $this->assertEquals([], $method->invoke($validator, ''));
    }

    public function testParseRulesWithMultipleRules(): void
    {
        $validator = Validator::make([], ['field' => 'required|min:5']);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('getParsedRules');
        $this->assertEquals(['required', 'min:5'], $method->invoke($validator, 'required|min:5'));
    }

    public function testApplyRulesWithRequiredFieldMissing(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $validator->validate();
        $this->assertArrayHasKey('name', $validator->errors());
        $this->assertStringContainsString('The name field is required.', $validator->errors()['name'][0]);
    }

    public function testApplyRulesWithNullableField(): void
    {
        $validator = Validator::make(['field' => null], ['field' => 'nullable|min:5']);
        $this->assertTrue($validator->validate());
    }

    public function testApplyRulesWithCustomClosureRule(): void
    {
        $validator = Validator::make(['score' => 80], [
            'score' => [function ($field, $value, $fail) {
                if ($value < 90) {
                    $fail("The {$field} must be at least 90.");
                }
            }]
        ]);
        $this->assertFalse($validator->validate());
        $this->assertArrayHasKey('score', $validator->errors());
        $this->assertStringContainsString('at least 90', $validator->errors()['score'][0]);
    }

    public function testApplyRulesWithUnknownRule(): void
    {
        $validator = Validator::make(['field' => 'value'], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('applyRules');
        $method->invoke($validator, 'field', 'value', ['unknown'], 'field', $validator->getData());
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString("Validation rule 'unknown' is not defined.", $validator->errors()['field'][0]);
    }

    public function testApplyRulesWithNonStringNonClosureRule(): void
    {
        $validator = Validator::make(['field' => 'value'], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('applyRules');
        $method->invoke($validator, 'field', 'value', [new \stdClass()], 'field', $validator->getData());
        $this->assertEmpty($validator->errors());
    }

    public function testValidateWildcardWithNonArrayNullable(): void
    {
        $data = ['users' => 'not-an-array'];
        $rules = ['users.*.name' => 'nullable'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertEmpty($validator->errors());
    }

    public function testValidateWildcardWithNonArrayCurrentPathEmpty(): void
    {
        $data = ['users' => 'not-an-array'];
        $rules = ['users.*' => 'required'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('users', $validator->errors());
        $this->assertStringContainsString('The users must be an array.', $validator->errors()['users'][0]);
    }

    public function testValidateWildcardWithNonArrayRequiredNonNullable(): void
    {
        $data = ['users' => 'not-an-array'];
        $rules = ['users.*.name' => 'required'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('users', $validator->errors());
        $this->assertStringContainsString('The users must be an array.', $validator->errors()['users'][0]);
    }

    public function testValidateWildcardWithArrayValue(): void
    {
        $data = ['users' => [['name' => 'John']]];
        $rules = ['users.*.name' => 'required'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertEmpty($validator->errors());
    }

    public function testResolveOtherFieldPathForComparisonWithWildcards(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('resolveOtherFieldPathForComparison');
        $result = $method->invoke($validator, 'users.*.status', 'users.0.profile.email');
        $this->assertEquals('users.0.status', $result);
    }

    public function testResolveOtherFieldPathForComparisonWithoutWildcards(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('resolveOtherFieldPathForComparison');
        $result = $method->invoke($validator, 'user.status', 'user.profile.email');
        $this->assertEquals('user.status', $result);
    }

    public function testResolveOtherFieldPathForComparisonWithMismatchedSegments(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('resolveOtherFieldPathForComparison');
        $result = $method->invoke($validator, 'other.*.data', 'user.profile');
        $this->assertEquals('other.profile.data', $result);
    }

    public function testFormatMessageWithMinRule(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('formatMessage');
        $template = 'The :attribute must be at least :min.';
        $result = $method->invoke($validator, $template, 'age', 'min', ['18']);
        $this->assertEquals('The age must be at least 18.', $result);
    }

    public function testFormatMessageWithBetweenRule(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('formatMessage');
        $template = 'The :attribute must be between :min and :max.';
        $result = $method->invoke($validator, $template, 'age', 'between', ['18', '65']);
        $this->assertEquals('The age must be between 18 and 65.', $result);
    }

    public function testFormatMessageWithInRule(): void
    {
        $validator = Validator::make([], ['status' => 'in:active,inactive']);
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('formatMessage');
        $message = $method->invoke($validator, 'The :attribute must be one of: :values.', 'status', 'in', ['active', 'inactive']);
        $this->assertEquals('The status must be one of: active, inactive.', $message);
    }

    public function testFormatMessageWithSameRule(): void
    {
        $validator = Validator::make([], [], [], ['other' => 'Other Field']);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('formatMessage');
        $template = 'The :attribute must match :other.';
        $result = $method->invoke($validator, $template, 'field', 'same', ['other']);
        $this->assertEquals('The field must match Other Field.', $result);
    }

    public function testFormatMessageSanityCheck(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('formatMessage');

        $template = ':values';
        $ruleParameters = ['active', 'inactive'];
        $result = $method->invoke($validator, $template, 'status', 'in', $ruleParameters);
        $this->assertEquals('active, inactive', $result, "Expected 'active, inactive', but got '$result'");
    }

    public function testAddErrorMessageByRuleNameWithCustomTemplate(): void
    {
        $messages = ['name.required' => 'Please provide a name.'];
        $validator = Validator::make([], ['name' => 'required'], $messages);
        $validator->validate();
        $this->assertEquals('Please provide a name.', $validator->errors()['name'][0]);
    }

    public function testAddErrorMessageByRuleNameWithArrayRule(): void
    {
        $validator = Validator::make(['field' => 'value'], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('addErrorMessageByRuleName');
        $method->invoke($validator, 'field', 'array', []);
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field must be an array.', $validator->errors()['field'][0]);
    }

    public function testRequiredRuleValidation(): void
    {
        $data = [];
        $rules = ['field' => 'required'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field field is required.', $validator->errors()['field'][0]);
    }

    public function testRequiredIfConditionMet(): void
    {
        $data = ['status' => 'active'];
        $rules = ['field' => 'required_if:status,active'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field field is required when status is active.', $validator->errors()['field'][0]);
    }

    public function testRequiredIfConditionNotMet(): void
    {
        $data = ['status' => 'inactive'];
        $rules = ['field' => 'required_if:status,active'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertEmpty($validator->errors());
    }

    public function testRequiredUnlessConditionMet(): void
    {
        $data = ['status' => 'inactive'];
        $rules = ['field' => 'required_unless:status,active'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertArrayHasKey('field', $validator->errors());
        $this->assertStringContainsString('The field field is required unless status is active.', $validator->errors()['field'][0]);
    }

    public function testRequiredUnlessConditionNotMet(): void
    {
        $data = ['status' => 'active'];
        $rules = ['field' => 'required_unless:status,active'];
        $validator = Validator::make($data, $rules);
        $validator->validate();
        $this->assertEmpty($validator->errors());
    }

    public function testGetFieldValueWithNestedData(): void
    {
        $data = ['user' => ['name' => 'John']];
        $validator = Validator::make($data, []);
        $this->assertEquals('John', $validator->getFieldValue('user.name'));
    }

    public function testGetFieldValueWithWildcard(): void
    {
        $validator = Validator::make([], []);
        $this->assertNull($validator->getFieldValue('users.*.name'));
    }

    public function testGetFieldValueCaching(): void
    {
        $data = ['user' => ['name' => 'John']];
        $validator = Validator::make($data, []);
        $validator->getFieldValue('user.name');
        $reflection = new ReflectionClass($validator);
        $cacheProp = $reflection->getProperty('fieldValueCache');
        $cache = $cacheProp->getValue($validator);
        $this->assertArrayHasKey('user.name', $cache);
        $this->assertEquals('John', $cache['user.name']);
    }

    public function testFailsWithErrors(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $validator->validate();
        $this->assertTrue($validator->fails());
    }

    public function testFailsTriggersValidationOnFirstUse(): void
    {
        $validator = Validator::make([], ['name' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testFailsWithoutErrors(): void
    {
        $validator = Validator::make(['name' => 'John'], ['name' => 'required']);
        $validator->validate();
        $this->assertFalse($validator->fails());
    }

    public function testPassesWithNoErrors(): void
    {
        $validator = Validator::make(['name' => 'John'], ['name' => 'required']);
        $validator->validate();
        $this->assertTrue($validator->passes());
    }

    public function testPassesTriggersValidationOnFirstUse(): void
    {
        // Invalid data: only a lazily-validating passes() can return false here.
        // v1.0 returned true because the errors array had not been populated yet.
        $validator = Validator::make([], ['name' => 'required']);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testValidationStateSurvivesSerializationRoundTrip(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $validator->validate();

        $restored = unserialize(serialize($validator));

        $this->assertTrue($restored->fails());
        $this->assertArrayHasKey('name', $restored->errors());
    }

    public function testHasValidatedStaysFalseWhenARuleThrowsMidValidation(): void
    {
        // Force RuleRegistry to finish its normal discovery pass first, so
        // injecting a throwing rule below does not get wiped out by a
        // deferred loadRules() the next time a rule is looked up.
        RuleRegistry::hasRule('required');

        $reflection = new ReflectionClass(RuleRegistry::class);
        $rulesProp = $reflection->getProperty('rules');
        $rules = $rulesProp->getValue();
        $rules['throwing_test_rule'] = new class implements ValidationRuleInterface {
            public function validate(string $field, $value, array $parameters, array $data): bool
            {
                throw new RuntimeException('boom mid-validation');
            }

            public function getErrorMessage(string $field, array $parameters, ?string $customMessage): string
            {
                return '';
            }

            public function validateParameters(string $field, array $parameters): void
            {
            }
        };
        $rulesProp->setValue(null, $rules);

        $validator = Validator::make(['name' => 'John'], ['name' => 'throwing_test_rule']);

        $threw = false;
        try {
            $validator->validate();
        } catch (RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected the injected rule to throw mid-validation.');

        $validatorReflection = new ReflectionClass($validator);
        $hasValidatedProp = $validatorReflection->getProperty('hasValidated');
        $this->assertFalse(
            $hasValidatedProp->getValue($validator),
            'hasValidated must stay false when validate() never completed.'
        );

        // A later passes() call must re-attempt validation (and therefore
        // re-throw against this still-broken rule set) rather than silently
        // reporting success from a stale, empty errors array.
        $threwAgain = false;
        try {
            $validator->passes();
        } catch (RuntimeException $e) {
            $threwAgain = true;
        }
        $this->assertTrue(
            $threwAgain,
            'passes() must re-attempt validation rather than report success after an incomplete run.'
        );
    }

    public function testRepeatedPassesCallsValidateOnlyOnce(): void
    {
        $validator = Validator::make(['name' => 'John'], ['name' => 'required']);

        $this->assertTrue($validator->passes());

        $reflection = new ReflectionClass($validator);
        $this->assertTrue($reflection->getProperty('hasValidated')->getValue($validator));
    }

    public function testPassesReturnsTrueForValidDataWithoutExplicitValidateCall(): void
    {
        $validator = Validator::make(['name' => 'John'], ['name' => 'required']);

        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->errors());
    }

    public function testPassesWithErrors(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $validator->validate();
        $this->assertFalse($validator->passes());
    }

    public function testGetDataReturnsOriginalData(): void
    {
        $data = ['user' => ['name' => 'John']];
        $validator = Validator::make($data, []);
        $this->assertSame($data, $validator->getData());
    }

    public function testSerializeAndUnserialize(): void
    {
        $data = ['name' => null];
        $rules = ['name' => 'required'];
        $messages = ['name.required' => 'Name is required'];
        $attributes = ['name' => 'Full Name'];
        $validator = Validator::make($data, $rules, $messages, $attributes, true);
        $validator->validate();

        $serialized = serialize($validator);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(Validator::class, $unserialized);
        $this->assertEquals($validator->getData(), $unserialized->getData());
        $this->assertEquals($validator->errors(), $unserialized->errors());
    }

    public function testUnserializeWithMissingData(): void
    {
        $serializedData = ['data' => ['name' => 'John']];
        $validator = new Validator([], []);
        $validator->__unserialize($serializedData);

        $this->assertEquals(['name' => 'John'], $validator->getData());
        $this->assertEquals([], $validator->errors());
        $this->assertFalse($validator->fails());
    }

    public function testImplodeSanityCheck(): void
    {
        $parameters = ['active', 'inactive'];
        $result = implode(', ', $parameters);
        $this->assertEquals('active, inactive', $result, "Expected 'active, inactive', but got '$result'");
    }

    public function testDynamicChunkSizeForSmallDataset(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $chunkSize = $method->invoke($validator, 500);
        $this->assertEquals(500, $chunkSize, 'Chunk size should equal dataset size for small datasets');
    }

    public function testDynamicChunkSizeForMediumDataset(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $chunkSize = $method->invoke($validator, 2000);
        $this->assertEquals(1000, $chunkSize, 'Chunk size should be 1000 for medium datasets');
    }

    public function testDynamicChunkSizeForLargeDataset(): void
    {
        $validator = Validator::make([], []);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $chunkSize = $method->invoke($validator, 5000);
        $this->assertEquals(500, $chunkSize, 'Chunk size should be 500 for large datasets');
    }

    public function testDynamicChunkSizeIsUsedWhenConfiguredChunkSizeIsNull(): void
    {
        $config = new DataValidationConfig();
        $config->chunkSize = null;

        $validator = Validator::make([], [], [], [], false, $config);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $chunkSize = $method->invoke($validator, 2000);
        $this->assertEquals(1000, $chunkSize, 'Dynamic chunk sizing should be used when config chunk size is null');
    }

    public function testConfiguredChunkSizeIsNormalizedToAtLeastOne(): void
    {
        $config = new DataValidationConfig();
        $config->chunkSize = 0;

        $validator = Validator::make([], [], [], [], false, $config);
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $chunkSize = $method->invoke($validator, 2000);
        $this->assertEquals(1, $chunkSize, 'Configured chunk size should be clamped to a positive value');
    }

    public function testDeterminesChunkSizeDynamicallyWhenNoConfigProvided(): void
    {
        $validator = Validator::make([], []);

        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('determineChunkSize');

        $this->assertSame(1000, $method->invoke($validator, 2000));
        $this->assertSame(500, $method->invoke($validator, 5000));
    }

    public function testValidateWithDynamicChunkSize(): void
    {
        $data = ['users' => array_fill(0, 1500, ['name' => 'John'])];
        $rules = ['users.*.name' => 'required'];
        $validator = Validator::make($data, $rules);

        $startMem = memory_get_usage(true);
        $validator->validate();
        $endMem = memory_get_usage(true);

        $this->assertEmpty($validator->errors(), 'Validation should pass with no errors');
        $memoryUsed = ($endMem - $startMem) / (1024 * 1024);
        $this->assertLessThanOrEqual(2, $memoryUsed, 'Memory usage should be low with dynamic chunking');
    }

    public function testValidatorIsValid(): void
    {
        $data = ['name' => 'John'];
        $rules = ['name' => 'required|string'];
        $this->assertTrue(Validator::isValid($data, $rules));
    }

    public function testValidatorIsValidSupportsConfigObject(): void
    {
        $data = ['users' => [['name' => 'John']]];
        $rules = ['users.*.name' => 'required|string'];
        $config = new DataValidationConfig();
        $config->chunkSize = null;

        $this->assertTrue(Validator::isValid($data, $rules, [], [], false, $config));
    }

    public function testIsValidThreadsTheConfigThroughToTheValidator(): void
    {
        // isValid() returns bool, so no instance is reachable to inspect.
        // The Validator constructor copies parsedRulesCache into a STATIC
        // cache limit, and that side effect outlives the call -- it is the
        // only way to prove isValid() forwarded its config, because PHP
        // silently discards extra arguments and a dropped config leaves no
        // other trace. setUp() resets this static to 500.
        //
        // NOTE: this couples the test to the static-leak behaviour that
        // Workstream 2.1 is scheduled to remove. When $cacheLimit becomes
        // instance-scoped, this test must be revisited.
        $reflection = new ReflectionClass(Validator::class);
        $cacheLimit = $reflection->getProperty('cacheLimit');

        $config = new DataValidationConfig();
        $config->parsedRulesCache = 77;

        Validator::isValid(['name' => 'John'], ['name' => 'required'], [], [], false, $config);

        $this->assertSame(
            77,
            $cacheLimit->getValue(),
            'isValid() must forward its config to the Validator instance.'
        );
    }

    public function testParsedRulesCacheEvictionKeepsTheCacheBounded(): void
    {
        $config = new DataValidationConfig();

        // A limit of 1 is the value at which the implementations diverge.
        // v1.0 computes -1/2 = -0.5, which truncates to offset 0, so
        // array_slice() returns the WHOLE array and the cache never shrinks.
        // The fix computes max(1, intdiv(1, 2)) = 1 and retains one entry.
        $config->parsedRulesCache = 1;

        $validator = Validator::make([], [], [], [], false, $config);
        $validator->clearParsedRulesCache();

        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('getParsedRules');

        // Push more distinct rule strings through than the cache can hold.
        for ($i = 0; $i < 12; $i++) {
            $method->invoke($validator, "required|string|min:{$i}");
        }

        $cache = $reflection->getProperty('parsedRulesCache')->getValue();

        $this->assertLessThanOrEqual(
            2,
            count($cache),
            'Cache must stay bounded. v1.0 grew to 12 because a float offset of -0.5 truncated to 0.'
        );
        $this->assertNotEmpty($cache);
    }
}
