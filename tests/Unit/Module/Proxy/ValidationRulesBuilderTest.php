<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Services\ValidationRulesBuilder;
use Tests\TestCase;

final class ValidationRulesBuilderTest extends TestCase
{
    private ValidationRulesBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ValidationRulesBuilder;
    }

    public function test_required_is_prepended_when_field_is_required_and_rules_lack_presence(): void
    {
        $fields = [
            ProxyField::make('phone')->required()->rules(['string', 'max:30']),
        ];

        $rules = $this->builder->build($fields);

        $this->assertSame(['required', 'string', 'max:30'], $rules['phone']);
    }

    public function test_required_is_not_duplicated_when_already_present_in_rules(): void
    {
        $fields = [
            ProxyField::make('phone')->required()->rules(['required', 'string']),
        ];

        $rules = $this->builder->build($fields);

        $this->assertSame(['required', 'string'], $rules['phone']);
    }

    public function test_required_is_not_added_when_field_has_present_or_filled_rules(): void
    {
        $present = ProxyField::make('a')->required()->rules(['present', 'string']);
        $filled = ProxyField::make('b')->required()->rules(['filled', 'string']);

        $rulesA = $this->builder->build([$present]);
        $rulesB = $this->builder->build([$filled]);

        $this->assertNotContains('required', $rulesA['a']);
        $this->assertNotContains('required', $rulesB['b']);
    }

    public function test_nullable_is_prepended_for_nullable_field(): void
    {
        $fields = [
            ProxyField::make('comment')->nullable()->rules(['string', 'max:1000']),
        ];

        $rules = $this->builder->build($fields);

        $this->assertSame(['nullable', 'string', 'max:1000'], $rules['comment']);
    }

    public function test_nullable_is_not_duplicated_when_already_present_in_rules(): void
    {
        $fields = [
            ProxyField::make('email')->nullable()->rules(['nullable', 'email']),
        ];

        $rules = $this->builder->build($fields);

        $this->assertSame(['nullable', 'email'], $rules['email']);
    }

    public function test_field_without_rules_or_required_nullable_is_excluded(): void
    {
        $fields = [ProxyField::make('meta')];

        $rules = $this->builder->build($fields);

        $this->assertArrayNotHasKey('meta', $rules);
    }

    public function test_empty_fields_return_empty_rules_array(): void
    {
        $rules = $this->builder->build([]);

        $this->assertSame([], $rules);
    }

    public function test_multiple_fields_produce_separate_rule_entries(): void
    {
        $fields = [
            ProxyField::make('phone')->required()->rules(['string']),
            ProxyField::make('email')->nullable()->rules(['email']),
        ];

        $rules = $this->builder->build($fields);

        $this->assertArrayHasKey('phone', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('required', $rules['phone']);
        $this->assertContains('nullable', $rules['email']);
    }
}
