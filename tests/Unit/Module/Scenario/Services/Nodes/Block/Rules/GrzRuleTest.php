<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes\Block\Rules;

use Module\Scenario\Services\Nodes\Block\Rules\GrzRule;
use Tests\TestCase;

final class GrzRuleTest extends TestCase
{
    public function test_passes_uppercase_string(): void
    {
        $this->assertPasses(new GrzRule(true), 'А123ВС777');
    }

    public function test_passes_valid_grz_shape(): void
    {
        $this->assertPasses(new GrzRule(true), [
            'country' => 'RU',
            'formatted' => 'А123ВС 777',
            'original' => 'А123ВС777',
        ]);
    }

    public function test_fails_lowercase_string(): void
    {
        $message = $this->assertFails(new GrzRule(true), 'а123вс777');
        $this->assertStringContainsString('верхнем регистре', $message);
    }

    public function test_fails_lowercase_shape_original(): void
    {
        $this->assertFails(new GrzRule(true), [
            'country' => 'RU',
            'formatted' => 'а123вс 777',
            'original' => 'а123вс777',
        ]);
    }

    public function test_fails_disallowed_characters(): void
    {
        $this->assertFails(new GrzRule(true), 'A123-BC777');
    }

    public function test_required_fails_when_empty(): void
    {
        $message = $this->assertFails(new GrzRule(true), '');
        $this->assertStringContainsString('обязательно', $message);
    }

    public function test_required_fails_when_shape_original_empty(): void
    {
        $this->assertFails(new GrzRule(true), ['country' => 'RU', 'formatted' => '', 'original' => '']);
    }

    public function test_not_required_passes_when_empty(): void
    {
        $this->assertPasses(new GrzRule(false), '');
    }

    public function test_not_required_passes_when_null(): void
    {
        $this->assertPasses(new GrzRule(false), null);
    }

    private function assertPasses(GrzRule $rule, mixed $value): void
    {
        $failed = false;
        $rule->validate('grz', $value, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected GrzRule to pass but it failed.');
    }

    private function assertFails(GrzRule $rule, mixed $value): string
    {
        $message = null;
        $rule->validate('grz', $value, function (string $key, ?string $msg = null) use (&$message): void {
            $message = $msg ?? $key;
        });

        $this->assertNotNull($message, 'Expected GrzRule to fail but it passed.');

        return $message;
    }
}
