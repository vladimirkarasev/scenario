<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services\Expression;

use App\Services\Expression\ExpressionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ExpressionServiceTest extends TestCase
{
    private ExpressionService $expressions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expressions = app(ExpressionService::class);
    }

    /** @param array<string, mixed> $context */
    #[DataProvider('arithmeticExpressions')]
    public function test_evaluates_arithmetic_and_boolean_expressions(
        string $expression,
        array $context,
        mixed $expected,
    ): void {
        $this->assertSame($expected, $this->expressions->evaluate($expression, $context));
    }

    /** @return iterable<string, array{string, array<string, mixed>, mixed}> */
    public static function arithmeticExpressions(): iterable
    {
        yield 'addition and multiplication precedence' => ['price + count * 2', ['price' => 10, 'count' => 5], 20];
        yield 'parentheses' => ['(price + count) * 2', ['price' => 10, 'count' => 5], 30];
        yield 'division' => ['total / count', ['total' => 15, 'count' => 3], 5];
        yield 'modulo' => ['number % 2', ['number' => 7], 1];
        yield 'unary minus' => ['-amount', ['amount' => 4], -4];
        yield 'greater than' => ['age >= 18', ['age' => 18], true];
        yield 'compound boolean true' => ['age >= 18 and score > 70', ['age' => 20, 'score' => 80], true];
        yield 'compound boolean false' => ['age >= 18 and score > 70', ['age' => 17, 'score' => 80], false];
        yield 'or expression' => ['role == "admin" or score >= 100', ['role' => 'user', 'score' => 100], true];
        yield 'nested property' => ['order.total - order.discount', ['order' => ['total' => 100, 'discount' => 15]], 85];
        yield 'wrapped expression' => ['{{ a + b }}', ['a' => 2, 'b' => 3], 5];
        yield 'dollar wrapped expression' => ['${ a * b }', ['a' => 4, 'b' => 3], 12];
    }

    #[DataProvider('wrappedExpressionCases')]
    public function test_detects_fully_wrapped_expressions(string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->expressions->isWrappedExpression($value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function wrappedExpressionCases(): iterable
    {
        yield 'braces' => ['{{ _condition.value >= 18 }}', true];
        yield 'dollar braces' => ['${ _condition.value >= 18 }', true];
        yield 'trimmed' => ['  {{ value }}  ', true];
        yield 'plain expression' => ['_condition.value >= 18', false];
        yield 'template text' => ['Age: {{ _condition.value }}', false];
    }

    /** @param array<string, mixed> $context */
    #[DataProvider('functionExpressions')]
    public function test_registered_functions(string $expression, array $context, mixed $expected): void
    {
        $this->assertSame($expected, $this->expressions->evaluate($expression, $context));
    }

    /** @return iterable<string, array{string, array<string, mixed>, mixed}> */
    public static function functionExpressions(): iterable
    {
        yield 'upper' => ['upper(name)', ['name' => 'Иван'], 'ИВАН'];
        yield 'lower' => ['lower(name)', ['name' => 'ИВАН'], 'иван'];
        yield 'trim' => ['trim(name)', ['name' => '  Иван  '], 'Иван'];
        yield 'length unicode' => ['length(name)', ['name' => 'Иван'], 4];
        yield 'concat scalars' => ['concat(prefix, number, suffix)', ['prefix' => '#', 'number' => 42, 'suffix' => '!'], '#42!'];
        yield 'replace' => ['replace(phone, " ", "")', ['phone' => '+7 900 000'], '+7900000'];
        yield 'implode' => ['implode(", ", roles)', ['roles' => ['admin', 'editor']], 'admin, editor'];
        yield 'pluck nested' => [
            'pluck(items, "data.city")',
            ['items' => [['data' => ['city' => 'Москва']], ['data' => ['city' => 'Казань']]]],
            ['Москва', 'Казань'],
        ];
        yield 'date format' => ['dateFormat(date, "DD.MM.YYYY")', ['date' => '2026-06-24T10:30:00+00:00'], '24.06.2026'];
        yield 'add time' => ['addTime(date, "1d")', ['date' => '2026-06-24T10:30:00+00:00'], '2026-06-25T10:30:00+00:00'];
        yield 'compound duration' => ['addTime(date, "1d 2h 30m")', ['date' => '2026-06-24T10:00:00+00:00'], '2026-06-25T12:30:00+00:00'];
    }

    public function test_render_resolves_multiple_expressions_recursively(): void
    {
        $result = $this->expressions->render([
            'title' => '{{ upper(user.name) }}',
            'amount' => 'Итого: {{ price * count }}',
            'allowed' => '${ age >= 18 }',
        ], [
            'user' => ['name' => 'Иван'],
            'price' => 15,
            'count' => 3,
            'age' => 20,
        ]);

        $this->assertSame([
            'title' => 'ИВАН',
            'amount' => 'Итого: 45',
            'allowed' => 'true',
        ], $result);
    }

    public function test_render_stringifies_supported_shapes(): void
    {
        $this->assertSame(
            'Телефон: +7 900 000-00-00',
            $this->expressions->render('Телефон: {{ phone }}', [
                'phone' => ['country' => 'RU', 'formatted' => '+7 900 000-00-00', 'original' => '79000000000'],
            ]),
        );
        $this->assertSame(
            'Город: Москва',
            $this->expressions->render('Город: {{ city }}', [
                'city' => ['value' => 'msk', 'label' => 'Москва'],
            ]),
        );
        $this->assertSame(
            'Города: Москва, Казань',
            $this->expressions->render('Города: {{ cities }}', [
                'cities' => [
                    ['value' => 'msk', 'label' => 'Москва'],
                    ['value' => 'kzn', 'label' => 'Казань'],
                ],
            ]),
        );
    }

    public function test_missing_nested_value_renders_blank_without_crashing(): void
    {
        $this->assertSame('Имя: ', $this->expressions->render('Имя: {{ user.profile.name }}', []));
    }

    public function test_array_suffix_renders_json_array(): void
    {
        $this->assertSame(
            '["admin","editor"]',
            $this->expressions->render('{{ roles._array }}', ['roles' => ['admin', 'editor']]),
        );
    }
}
