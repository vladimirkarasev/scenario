<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services\Expression\Functions;

use App\Services\Expression\Functions\IsElseFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsElseFunctionTest extends TestCase
{
    public function test_always_returns_true(): void
    {
        $function = new IsElseFunction;

        $this->assertTrue($function->evaluate([]));
        $this->assertTrue($function->evaluate(['matched' => true], false));
    }

    #[DataProvider('expressionCases')]
    public function test_detects_standalone_else_expression(string $expression, bool $expected): void
    {
        $this->assertSame($expected, IsElseFunction::matches($expression));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function expressionCases(): iterable
    {
        yield 'plain' => ['isElse()', true];
        yield 'wrapped' => ['{{ isElse() }}', true];
        yield 'dollar wrapped' => ['${ isElse() }', true];
        yield 'whitespace' => [' {{  isElse ( )  }} ', true];
        yield 'compound expression' => ['{{ isElse() and allowed }}', false];
        yield 'other function' => ['{{ isEmpty() }}', false];
    }
}
