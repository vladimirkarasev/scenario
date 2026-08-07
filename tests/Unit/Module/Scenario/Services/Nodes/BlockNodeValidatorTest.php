<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Validation\ValidationException;
use Module\Scenario\Services\Nodes\Block\BlockNodeValidator;
use Tests\TestCase;

final class BlockNodeValidatorTest extends TestCase
{
    private BlockNodeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BlockNodeValidator;
    }

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
        $this->assertStringContainsString('email', mb_strtolower((string) $e->errors()['email'][0]));
    }

    public function test_email_field_passes_when_nullable_and_absent(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('email', 'email', required: false)]),
            [],
        );
    }

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

    public function test_vin_field_passes_valid_vin(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('vin', 'vin', required: true)]),
            ['vin' => 'X4XCM1950L0000001'],
        );
    }

    public function test_vin_field_fails_lowercase_vin(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('vin', 'vin', required: true)]),
            ['vin' => 'x4xcm1950l0000001'],
        );

        $this->assertArrayHasKey('vin', $e->errors());
        $this->assertStringContainsString('VIN', $e->errors()['vin'][0]);
    }

    public function test_vin_field_fails_when_required_and_missing(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('vin', 'vin', required: true)]),
            [],
        );

        $this->assertArrayHasKey('vin', $e->errors());
    }

    public function test_vin_field_passes_shape_value(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('vin', 'vin', required: true)]),
            ['vin' => ['value' => 'X4XCM1950L0000001']],
        );
    }

    public function test_grz_field_passes_uppercase_grz(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('grz', 'grz', required: true)]),
            ['grz' => 'А123ВС777'],
        );
    }

    public function test_grz_field_fails_lowercase_grz(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('grz', 'grz', required: true)]),
            ['grz' => 'а123вс777'],
        );

        $this->assertArrayHasKey('grz', $e->errors());
        $this->assertStringContainsString('регистре', $e->errors()['grz'][0]);
    }

    public function test_grz_field_passes_shape_original(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('grz', 'grz', required: true)]),
            ['grz' => ['country' => 'RU', 'formatted' => 'А123ВС 777', 'original' => 'А123ВС777']],
        );
    }

    public function test_grz_field_fails_when_required_and_missing(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('grz', 'grz', required: true)]),
            [],
        );

        $this->assertArrayHasKey('grz', $e->errors());
    }

    public function test_map_point_field_passes_with_coordinates(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('place', 'map_point', required: true)]),
            ['place' => ['lat' => 55.75, 'lng' => 37.62]],
        );
    }

    public function test_map_point_field_fails_when_required_and_missing(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('place', 'map_point', required: true)]),
            [],
        );

        $this->assertArrayHasKey('place', $e->errors());
    }

    public function test_map_point_field_fails_when_coordinates_incomplete(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('place', 'map_point', required: true)]),
            ['place' => ['lat' => 55.75]],
        );

        $this->assertArrayHasKey('place', $e->errors());
    }

    public function test_map_point_field_passes_when_nullable_and_absent(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('place', 'map_point', required: false)]),
            [],
        );
    }

    public function test_route_field_passes_with_two_waypoints(): void
    {
        $this->assertPasses(
            $this->nodeData([$this->field('trip', 'route', required: true)]),
            ['trip' => ['waypoints' => [
                ['lat' => 55.75, 'lng' => 37.62],
                ['lat' => 59.93, 'lng' => 30.33],
            ]]],
        );
    }

    public function test_route_field_fails_with_single_waypoint(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('trip', 'route', required: true)]),
            ['trip' => ['waypoints' => [['lat' => 55.75, 'lng' => 37.62]]]],
        );

        $this->assertArrayHasKey('trip', $e->errors());
    }

    public function test_route_field_fails_when_required_and_missing(): void
    {
        $e = $this->assertFails(
            $this->nodeData([$this->field('trip', 'route', required: true)]),
            [],
        );

        $this->assertArrayHasKey('trip', $e->errors());
    }

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

    /** @param  array<array-key, mixed>  $fields */
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
        $this->assertTrue(true);
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
}
