<?php

namespace YorCreative\DataValidation\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YorCreative\DataValidation\RuleRegistry;
use YorCreative\DataValidation\Validator;

/**
 * Regression coverage for sibling-value aliasing during traversal.
 *
 * The traversal queue used to hold a reference to a single loop-local
 * variable, so every queued field read whichever sibling's value had been
 * assigned most recently rather than its own. The visible symptom was
 * order-dependent results, and -- worse -- invalid data passing whenever
 * the last sibling processed happened to be valid.
 */
class ValidatorSiblingValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(RuleRegistry::class);
        $reflection->getProperty('rules')->setValue(null, []);
        $reflection->getProperty('closureRules')->setValue(null, []);
        $reflection->getProperty('customRuleDirectories')->setValue(null, []);
        $reflection->getProperty('isInitialized')->setValue(null, false);

        $validatorReflection = new ReflectionClass(Validator::class);
        $validatorReflection->getProperty('parsedRulesCache')->setValue(null, []);
        $validatorReflection->getProperty('cacheLimit')->setValue(null, 500);
    }

    protected function tearDown(): void
    {
        gc_collect_cycles();
        parent::tearDown();
    }

    public function testInvalidFirstSiblingIsReportedWhenSecondIsValid(): void
    {
        $validator = Validator::make(
            ['a' => 'not-an-email', 'b' => 'good@example.com'],
            ['a' => 'email', 'b' => 'email']
        );

        $this->assertFalse($validator->validate(), 'Invalid sibling "a" must fail validation');
        $this->assertArrayHasKey('a', $validator->errors(), 'Error expected for "a"');
        $this->assertArrayNotHasKey('b', $validator->errors(), 'No error expected for valid "b"');
    }

    public function testInvalidSecondSiblingIsReportedWhenFirstIsValid(): void
    {
        $validator = Validator::make(
            ['a' => 'good@example.com', 'b' => 'not-an-email'],
            ['a' => 'email', 'b' => 'email']
        );

        $this->assertFalse($validator->validate(), 'Invalid sibling "b" must fail validation');
        $this->assertArrayHasKey('b', $validator->errors(), 'Error expected for "b"');
        $this->assertArrayNotHasKey('a', $validator->errors(), 'No error expected for valid "a"');
    }

    public function testResultsAreIndependentOfRuleOrdering(): void
    {
        $data = ['a' => 'not-an-email', 'b' => 'good@example.com'];

        $forward = Validator::make($data, ['a' => 'email', 'b' => 'email']);
        $forward->validate();

        $reversed = Validator::make($data, ['b' => 'email', 'a' => 'email']);
        $reversed->validate();

        $this->assertSame(
            array_keys($forward->errors()),
            array_keys($reversed->errors()),
            'Reversing rule order must not change which fields fail'
        );
        $this->assertSame(['a'], array_keys($forward->errors()));
    }

    public function testEachSiblingIsValidatedAgainstItsOwnValue(): void
    {
        $validator = Validator::make(
            ['a' => 'bad-one', 'b' => 'bad-two', 'c' => 'good@example.com'],
            ['a' => 'email', 'b' => 'email', 'c' => 'email']
        );

        $validator->validate();

        $this->assertSame(
            ['a', 'b'],
            array_keys($validator->errors()),
            'Both invalid siblings must fail even though the last sibling is valid'
        );
    }

    public function testNestedSiblingsRetainTheirOwnValues(): void
    {
        $validator = Validator::make(
            [
                'user' => ['email' => 'not-an-email'],
                'admin' => ['email' => 'good@example.com'],
            ],
            [
                'user.email' => 'email',
                'admin.email' => 'email',
            ]
        );

        $validator->validate();

        $this->assertArrayHasKey('user.email', $validator->errors());
        $this->assertArrayNotHasKey('admin.email', $validator->errors());
    }

    public function testWildcardRowsRetainTheirOwnValues(): void
    {
        $validator = Validator::make(
            ['users' => [
                ['email' => 'not-an-email'],
                ['email' => 'good@example.com'],
            ]],
            ['users.*.email' => 'email']
        );

        $validator->validate();

        $this->assertArrayHasKey('users.0.email', $validator->errors());
        $this->assertArrayNotHasKey('users.1.email', $validator->errors());
    }

    public function testWildcardRowsAlongsideScalarSiblingRule(): void
    {
        $validator = Validator::make(
            [
                'users' => [['email' => 'not-an-email'], ['email' => 'good@example.com']],
                'title' => 'a title',
            ],
            [
                'users.*.email' => 'email',
                'title' => 'string',
            ]
        );

        $validator->validate();

        $this->assertArrayHasKey('users.0.email', $validator->errors());
        $this->assertArrayNotHasKey('users.1.email', $validator->errors());
        $this->assertArrayNotHasKey('title', $validator->errors());
    }

    public function testSameRuleComparesTheCorrectFieldsAlongsideSiblings(): void
    {
        $matching = Validator::make(
            ['pw' => 'secret', 'pw_confirm' => 'secret', 'other' => 'bad-email'],
            ['pw_confirm' => 'same:pw', 'other' => 'email']
        );
        $matching->validate();

        $this->assertArrayNotHasKey('pw_confirm', $matching->errors(), '"same" must still match');
        $this->assertArrayHasKey('other', $matching->errors());

        $mismatched = Validator::make(
            ['pw' => 'secret', 'pw_confirm' => 'different', 'other' => 'ok@example.com'],
            ['pw_confirm' => 'same:pw', 'other' => 'email']
        );
        $mismatched->validate();

        $this->assertArrayHasKey('pw_confirm', $mismatched->errors(), '"same" must still detect a mismatch');
        $this->assertArrayNotHasKey('other', $mismatched->errors());
    }

    public function testDifferentRuleComparesTheCorrectFieldsAlongsideSiblings(): void
    {
        $distinct = Validator::make(
            ['a' => 'x', 'b' => 'y', 'other' => 'bad-email'],
            ['b' => 'different:a', 'other' => 'email']
        );
        $distinct->validate();

        $this->assertArrayNotHasKey('b', $distinct->errors(), '"different" must still pass for distinct values');
        $this->assertArrayHasKey('other', $distinct->errors());

        $identical = Validator::make(
            ['a' => 'x', 'b' => 'x', 'other' => 'ok@example.com'],
            ['b' => 'different:a', 'other' => 'email']
        );
        $identical->validate();

        $this->assertArrayHasKey('b', $identical->errors(), '"different" must still detect identical values');
        $this->assertArrayNotHasKey('other', $identical->errors());
    }

    public function testConditionalRequiredRuleReadsTheCorrectFields(): void
    {
        $triggered = Validator::make(
            ['type' => 'company', 'company_name' => null, 'contact' => 'bad-email'],
            ['company_name' => 'required_if:type,company', 'contact' => 'email']
        );
        $triggered->validate();

        $this->assertArrayHasKey('company_name', $triggered->errors(), 'required_if must trigger');
        $this->assertArrayHasKey('contact', $triggered->errors());

        $notTriggered = Validator::make(
            ['type' => 'person', 'company_name' => null, 'contact' => 'ok@example.com'],
            ['company_name' => 'required_if:type,company', 'contact' => 'email']
        );
        $notTriggered->validate();

        $this->assertEmpty($notTriggered->errors(), 'required_if must not trigger for a non-matching value');
    }

    public function testStopOnFirstErrorStillReportsTheFirstFailingField(): void
    {
        $validator = Validator::make(
            ['a' => 'not-an-email', 'b' => 'also-bad'],
            ['a' => 'email', 'b' => 'email'],
            [],
            [],
            true
        );

        $this->assertFalse($validator->validate(), 'stopOnFirstError must still fail');
        $this->assertCount(1, $validator->errors(), 'stopOnFirstError must report exactly one field');
        $this->assertArrayHasKey('a', $validator->errors());
    }

    public function testDeeplyNestedSiblingBranchesDoNotShareValues(): void
    {
        $validator = Validator::make(
            [
                'a' => ['b' => ['c' => 'not-an-email']],
                'x' => ['y' => ['z' => 'good@example.com']],
            ],
            [
                'a.b.c' => 'email',
                'x.y.z' => 'email',
            ]
        );

        $validator->validate();

        $this->assertArrayHasKey('a.b.c', $validator->errors());
        $this->assertArrayNotHasKey('x.y.z', $validator->errors());
    }
}
