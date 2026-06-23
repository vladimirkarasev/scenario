<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services\Expression\Functions;

use App\Services\Expression\ExpressionService;
use App\Services\Expression\Functions\PluckFunction;
use Tests\TestCase;

/**
 * Покрываем баг из run #42: {{ implode(" / ", pluck(Справочник, "data.gorod")) }}
 * возвращал " / " вместо "Челябинск / Белгород", потому что у multiple-справочника
 * массив объектов нужно было корректно итерировать.
 */
final class PluckFunctionTest extends TestCase
{
    private PluckFunction $pluck;
    private ExpressionService $expressions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluck = new PluckFunction();
        $this->expressions = app(ExpressionService::class);
    }

    public function test_pluck_extracts_field_from_list_of_objects(): void
    {
        $items = [
            ['id' => '736', 'data' => ['gorod' => 'Челябинск']],
            ['id' => '652', 'data' => ['gorod' => 'Белгород']],
        ];

        $this->assertSame(['Челябинск', 'Белгород'], $this->pluck->evaluate([], $items, 'data.gorod'));
    }

    public function test_pluck_supports_top_level_field(): void
    {
        $items = [['id' => '1', 'label' => 'A'], ['id' => '2', 'label' => 'B']];

        $this->assertSame(['1', '2'], $this->pluck->evaluate([], $items, 'id'));
    }

    public function test_pluck_handles_missing_path_as_null(): void
    {
        $items = [['data' => ['gorod' => 'X']], ['data' => []]];

        $this->assertSame(['X', null], $this->pluck->evaluate([], $items, 'data.gorod'));
    }

    public function test_pluck_returns_empty_for_non_array_input(): void
    {
        $this->assertSame([], $this->pluck->evaluate([], null, 'data.gorod'));
        $this->assertSame([], $this->pluck->evaluate([], 'not-an-array', 'id'));
    }

    public function test_pluck_returns_empty_when_path_missing(): void
    {
        $this->assertSame([], $this->pluck->evaluate([], [['id' => '1']], ''));
        $this->assertSame([], $this->pluck->evaluate([], [['id' => '1']], null));
    }

    /**
     * Single-mode справочник кладётся в context как один assoc-объект, а не как list.
     * pluck должен обернуть его в список — чтобы `pluck(Справочник, "data.gorod")`
     * работал одинаково и для single, и для multiple.
     */
    public function test_pluck_treats_single_object_as_one_element_list(): void
    {
        $single = ['id' => '736', 'data' => ['gorod' => 'Челябинск']];

        $this->assertSame(['Челябинск'], $this->pluck->evaluate([], $single, 'data.gorod'));
    }

    public function test_implode_with_pluck_renders_multiple_dealer_cities(): void
    {
        $context = $this->directoryContextFromRunFixture();

        $result = $this->expressions->render(
            '{{ implode(" / ", pluck(Справочник, "data.gorod")) }}',
            $context,
        );

        $this->assertSame('Челябинск / Белгород', $result);
    }

    public function test_implode_with_pluck_works_for_single_select(): void
    {
        $context = [
            'Справочник' => ['id' => '736', 'data' => ['gorod' => 'Челябинск']],
        ];

        $result = $this->expressions->render(
            '{{ implode(", ", pluck(Справочник, "data.gorod")) }}',
            $context,
        );

        $this->assertSame('Челябинск', $result);
    }

    public function test_implode_with_empty_directory_renders_blank(): void
    {
        $context = ['Справочник' => []];

        $result = $this->expressions->render(
            '{{ implode(" / ", pluck(Справочник, "data.gorod")) }}',
            $context,
        );

        $this->assertSame('', $result);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function directoryContextFromRunFixture(): array
    {
        return [
            'Справочник' => [
                [
                    'id' => '736',
                    'label' => 'ул. Игуменка, д.173, стр. 1',
                    'data' => [
                        'gorod' => 'Челябинск',
                        'region' => 'Челябинская область',
                        'naimenovanie-dilera' => 'Сильвер Челябинск',
                    ],
                    'parent_id' => null,
                    'external_key' => '106d568594cc77a3f03981a27162e0dd',
                ],
                [
                    'id' => '652',
                    'label' => 'ул. Студенческая, д.1ш',
                    'data' => [
                        'gorod' => 'Белгород',
                        'region' => 'Белгородская область',
                        'naimenovanie-dilera' => 'Ринг Белгород',
                    ],
                    'parent_id' => null,
                    'external_key' => '85483607b9ed5477882b453b0db448c3',
                ],
            ],
        ];
    }
}
