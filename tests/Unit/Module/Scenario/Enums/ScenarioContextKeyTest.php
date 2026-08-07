<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Enums;

use Module\Scenario\Enums\ScenarioContextKey;
use PHPUnit\Framework\TestCase;

final class ScenarioContextKeyTest extends TestCase
{
    public function test_all_reserved_context_keys_are_system_variables(): void
    {
        foreach (ScenarioContextKey::cases() as $key) {
            $this->assertTrue(ScenarioContextKey::isSystem($key->value));
            $this->assertTrue(ScenarioContextKey::isReserved($key->value));
        }
    }

    public function test_user_variable_is_not_system_or_reserved(): void
    {
        $this->assertFalse(ScenarioContextKey::isSystem('varName'));
        $this->assertFalse(ScenarioContextKey::isReserved('varName'));
        $this->assertTrue(ScenarioContextKey::isSystem('_custom'));
        $this->assertFalse(ScenarioContextKey::isReserved('_custom'));
    }
}
