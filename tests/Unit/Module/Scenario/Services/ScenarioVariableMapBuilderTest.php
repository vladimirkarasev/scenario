<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Module\Scenario\Services\ScenarioVariableMapBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ScenarioVariableMapBuilderTest extends TestCase
{
    private ScenarioVariableMapBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ScenarioVariableMapBuilder;
    }

    #[DataProvider('fieldCases')]
    public function test_builds_metadata_for_each_field_type(array $field, array $expected): void
    {
        $map = $this->builder->build([
            'blocks' => [[
                'id' => 'block',
                'type' => 'block',
                'data' => ['fields' => [$field]],
            ]],
        ]);

        $this->assertSame($expected, $map[$field['varName']] ?? null);
    }

    public static function fieldCases(): iterable
    {
        yield 'simple' => [
            ['name' => 'age', 'varName' => 'age', 'type' => 'number'],
            ['_block_id' => 'block', '_field_name' => 'age', '_field_type' => 'number'],
        ];
        yield 'select' => [
            [
                'name' => 'city',
                'varName' => 'city',
                'type' => 'select',
                'options' => [['value' => 'msk', 'label' => 'Москва'], 'invalid'],
            ],
            [
                '_block_id' => 'block',
                '_field_name' => 'city',
                '_field_type' => 'select',
                '_options' => [['value' => 'msk', 'label' => 'Москва']],
            ],
        ];
        yield 'datetime' => [
            ['name' => 'created', 'varName' => 'created', 'type' => 'datetime', 'format' => 'DD.MM.YYYY'],
            [
                '_block_id' => 'block',
                '_field_name' => 'created',
                '_field_type' => 'datetime',
                '_format' => 'DD.MM.YYYY',
            ],
        ];
        yield 'directory' => [
            [
                'name' => 'dealer',
                'varName' => 'dealer',
                'type' => 'directory_list',
                'directoryId' => 'directory',
                'versionId' => 'version',
                'labelTemplate' => '{{ name }}',
                'multiple' => true,
            ],
            [
                '_block_id' => 'block',
                '_field_name' => 'dealer',
                '_field_type' => 'directory_list',
                '_directory_id' => 'directory',
                '_version_id' => 'version',
                '_label_template' => '{{ name }}',
                '_multiple' => true,
            ],
        ];
    }

    public function test_supports_legacy_nodes_key(): void
    {
        $map = $this->builder->build([
            'nodes' => [[
                'id' => 'legacy',
                'type' => 'block',
                'data' => ['fields' => [
                    ['name' => 'name', 'varName' => 'name', 'type' => 'input'],
                ]],
            ]],
        ]);

        $this->assertSame('legacy', $map['name']['_block_id'] ?? null);
    }

    public function test_skips_invalid_fields_and_non_block_nodes(): void
    {
        $map = $this->builder->build([
            'blocks' => [
                ['id' => 'start', 'type' => 'start', 'data' => ['fields' => [
                    ['name' => 'ignored', 'varName' => 'ignored', 'type' => 'input'],
                ]]],
                ['id' => 'block', 'type' => 'block', 'data' => ['fields' => [
                    null,
                    ['name' => '', 'varName' => 'missing_name', 'type' => 'input'],
                    ['name' => 'missing_var', 'varName' => '', 'type' => 'input'],
                ]]],
            ],
        ]);

        $this->assertSame([], $map);
    }

    public function test_last_duplicate_variable_wins(): void
    {
        $map = $this->builder->build([
            'blocks' => [
                ['id' => 'first', 'type' => 'block', 'data' => ['fields' => [
                    ['name' => 'value', 'varName' => 'shared', 'type' => 'input'],
                ]]],
                ['id' => 'second', 'type' => 'block', 'data' => ['fields' => [
                    ['name' => 'other', 'varName' => 'shared', 'type' => 'number'],
                ]]],
            ],
        ]);

        $this->assertSame('second', $map['shared']['_block_id'] ?? null);
        $this->assertSame('number', $map['shared']['_field_type'] ?? null);
    }
}
