<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario;

use Illuminate\Support\Facades\Validator;
use Module\Scenario\Rules\SafeFieldPresetSnapshot;
use Tests\TestCase;

final class SafeFieldPresetSnapshotTest extends TestCase
{
    public function test_accepts_normal_nested_field_configuration(): void
    {
        $validator = Validator::make([
            'field' => [
                'type' => 'select',
                'options' => [['id' => 'one', 'label' => 'Москва']],
            ],
        ], ['field' => [new SafeFieldPresetSnapshot()]]);

        self::assertFalse($validator->fails());
    }

    public function test_rejects_prototype_pollution_keys(): void
    {
        $validator = Validator::make([
            'field' => ['type' => 'input', '__proto__' => ['admin' => true]],
        ], ['field' => [new SafeFieldPresetSnapshot()]]);

        self::assertTrue($validator->fails());
    }

    public function test_rejects_oversized_configuration(): void
    {
        $validator = Validator::make([
            'field' => ['type' => 'input', 'value' => str_repeat('x', 70_000)],
        ], ['field' => [new SafeFieldPresetSnapshot()]]);

        self::assertTrue($validator->fails());
    }
}
