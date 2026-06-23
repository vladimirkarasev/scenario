<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Validation\ValidationException;
use Module\Scenario\Services\Nodes\BlockNodeValidator;
use Tests\TestCase;

final class BlockNodeValidatorTest extends TestCase
{
    private BlockNodeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BlockNodeValidator();
    }

    // ------------------------------------------------------------------ helpers

    /** @param array<array-key, mixed> $fields */
    private function nodeData(array $fields): array
    {
        return ['fields' => $fields];
    }

    private function field(string $name, string $type = 'input', bool $required = false, mixed ...$extra): array
    {
        return array_merge(['name' => $name, 'type' => $type, 'required' => $required], $extra);
    }

    private function assertPasses(array $nodeData, array $input): void
    {
        $this->validator->validate($nodeData, $input);
        $this->assertTrue(true); // reached without exception
    }

    private function assertFails(array $nodeData, array $input): ValidationException
    {
        try {
            $this->validator->validate($nodeData, $input);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            return $e;
        }
    }

    // ------------------------------------------------------------------ empty / no rules

    public function test_no_fields_passes_any_input(): void
    {
        $this->assertPasses($this->nodeData([]), ['anything' => 'value']);
    }

    public function test_field_without_name_is_skipped(): void
    {
        $this->assertPasses(
            $this->nodeData([['type' => 'input', 'required' => true]]),
            [],
        );
    }

    public function test_non_array_field_entry_is_skipped(): void
    {
        $this->assertPasses($this->nodeData(['not-an-array']), []);
    }

    // ------------------------------------------------------------------ required

    public function test_required_field_passes_when_present(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('name', required: true)]),
            ['name' => 'Alice'],
        );
    }

    public function test_required_field_fails_when_missing(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('name', required: true)]),
            [],
        );

        $this->assertArrayHasKey('name', $e->errors());
        $this->assertStringContainsString('обязательно', $e->errors()['name'][0]);
    }

    public function test_required_field_fails_when_empty_string(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('title', required: true)]),
            ['title' => ''],
        );

        $this->assertArrayHasKey('title', $e->errors());
    }

    // ------------------------------------------------------------------ nullable

    public function test_nullable_field_passes_when_absent(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('comment', required: false)]),
            [],
        );
    }

    public function test_nullable_field_passes_when_empty(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('comment', required: false)]),
            ['comment' => ''],
        );
    }

    // ------------------------------------------------------------------ checkbox

    public function test_required_checkbox_passes_when_accepted(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('agree', 'checkbox', required: true)]),
            ['agree' => true],
        );
    }

    public function test_required_checkbox_fails_when_not_accepted(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('agree', 'checkbox', required: true)]),
            ['agree' => false],
        );

        $this->assertArrayHasKey('agree', $e->errors());
        $this->assertStringContainsString('обязательно', $e->errors()['agree'][0]);
    }

    public function test_required_checkbox_fails_when_absent(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('agree', 'checkbox', required: true)]),
            [],
        );

        $this->assertArrayHasKey('agree', $e->errors());
    }

    // ------------------------------------------------------------------ email

    public function test_email_field_passes_valid_address(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('email', 'email', required: true)]),
            ['email' => 'user@example.com'],
        );
    }

    public function test_email_field_fails_invalid_address(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('email', 'email', required: true)]),
            ['email' => 'not-an-email'],
        );

        $this->assertArrayHasKey('email', $e->errors());
        $this->assertStringContainsString('email', mb_strtolower($e->errors()['email'][0]));
    }

    public function test_email_field_passes_when_nullable_and_absent(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('email', 'email', required: false)]),
            [],
        );
    }

    // ------------------------------------------------------------------ number

    public function test_number_field_passes_numeric_value(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('age', 'number', required: true)]),
            ['age' => '42'],
        );
    }

    public function test_number_field_fails_non_numeric_value(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('age', 'number', required: true)]),
            ['age' => 'twenty'],
        );

        $this->assertArrayHasKey('age', $e->errors());
        $this->assertStringContainsString('числом', $e->errors()['age'][0]);
    }

    // ------------------------------------------------------------------ date / datetime

    public function test_date_field_passes_valid_date(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('birthday', 'date', required: true)]),
            ['birthday' => '2000-01-15'],
        );
    }

    public function test_date_field_fails_invalid_date(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('birthday', 'date', required: true)]),
            ['birthday' => 'not-a-date'],
        );

        $this->assertArrayHasKey('birthday', $e->errors());
        $this->assertStringContainsString('дату', $e->errors()['birthday'][0]);
    }

    public function test_datetime_field_uses_date_rule(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('meeting_at', 'datetime', required: true)]),
            ['meeting_at' => '2025-06-01 14:30:00'],
        );
    }

    // ------------------------------------------------------------------ textarea maxLength

    public function test_textarea_passes_within_default_max_length(): void
    {
        $this->assertPasses(
            $this->nodeData([['name' => 'bio', 'type' => 'textarea', 'required' => false]]),
            ['bio' => str_repeat('a', 3000)],
        );
    }

    public function test_textarea_fails_when_exceeds_default_max_length(): void
    {
        $e = $this->assertFails(
            $this->nodeData([['name' => 'bio', 'type' => 'textarea', 'required' => false]]),
            ['bio' => str_repeat('a', 3001)],
        );

        $this->assertArrayHasKey('bio', $e->errors());
        $this->assertStringContainsString('3000', $e->errors()['bio'][0]);
    }

    public function test_textarea_respects_custom_max_length(): void
    {
        $nodeData = $this->nodeData([
            ['name' => 'note', 'type' => 'textarea', 'required' => false, 'maxLength' => 100],
        ]);

        $this->assertPasses($nodeData, ['note' => str_repeat('x', 100)]);

        $e = $this->assertFails($nodeData, ['note' => str_repeat('x', 101)]);
        $this->assertArrayHasKey('note', $e->errors());
        $this->assertStringContainsString('100', $e->errors()['note'][0]);
    }

    // ------------------------------------------------------------------ multiple fields

    public function test_multiple_fields_all_pass(): void
    {
        $this->assertPasses(
            $this->nodeData([
                $this->field('name', required: true),
                $this->field('email', 'email', required: true),
            ]),
            ['name' => 'Alice', 'email' => 'alice@example.com'],
        );
    }

    public function test_multiple_fields_collects_all_errors(): void
    {
        $e = $this->assertFails(
            $this->nodeData([
                $this->field('name', required: true),
                $this->field('email', 'email', required: true),
            ]),
            [],
        );

        $this->assertArrayHasKey('name', $e->errors());
        $this->assertArrayHasKey('email', $e->errors());
    }
}
