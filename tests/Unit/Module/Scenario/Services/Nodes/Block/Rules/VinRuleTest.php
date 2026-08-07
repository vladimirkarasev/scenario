<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes\Block\Rules;

use Module\Scenario\Services\Nodes\Block\Rules\VinRule;
use Tests\TestCase;

final class VinRuleTest extends TestCase
{
    public function test_passes_valid_vin_string(): void
    {
        $this->assertPasses(new VinRule(true), 'X4XCM1950L0000001');
    }

    public function test_passes_valid_vin_shape(): void
    {
        $this->assertPasses(new VinRule(true), ['value' => 'X4XCM1950L0000001']);
    }

    public function test_fails_lowercase_vin(): void
    {
        $message = $this->assertFails(new VinRule(true), 'x4xcm1950l0000001');
        $this->assertStringContainsString('VIN', $message);
    }

    public function test_fails_wrong_length(): void
    {
        $this->assertFails(new VinRule(true), 'X4XCM1950L00001');
    }

    public function test_fails_forbidden_letters(): void
    {
        // I, O, Q are not valid VIN characters
        $this->assertFails(new VinRule(true), 'IOQCM1950L0000001');
    }

    public function test_required_fails_when_empty(): void
    {
        $message = $this->assertFails(new VinRule(true), '');
        $this->assertStringContainsString('обязательно', $message);
    }

    public function test_required_fails_when_shape_value_empty(): void
    {
        $this->assertFails(new VinRule(true), ['value' => '']);
    }

    public function test_not_required_passes_when_empty(): void
    {
        $this->assertPasses(new VinRule(false), '');
    }

    public function test_not_required_passes_when_null(): void
    {
        $this->assertPasses(new VinRule(false), null);
    }

    private function assertPasses(VinRule $rule, mixed $value): void
    {
        $failed = false;
        $rule->validate('vin', $value, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected VinRule to pass but it failed.');
    }

    private function assertFails(VinRule $rule, mixed $value): string
    {
        $message = null;
        $rule->validate('vin', $value, function (string $key, ?string $msg = null) use (&$message): void {
            $message = $msg ?? $key;
        });

        $this->assertNotNull($message, 'Expected VinRule to fail but it passed.');

        return $message;
    }
}
