<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Module\Scenario\Services\Variables\VariableResolver;
use Tests\TestCase;

final class VariableResolverTest extends TestCase
{
    private VariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(VariableResolver::class);
    }

    public function test_resolve_value_evaluates_simple_expression(): void
    {
        $result = $this->resolver->resolveValue('score + 1', ['score' => 9]);

        $this->assertSame(10, $result);
    }

    public function test_resolve_renders_missing_variable_as_empty_string(): void
    {
        $result = $this->resolver->resolve('{{ missing_var }}', []);

        $this->assertSame('', $result);
    }

    public function test_resolve_renders_template_in_string(): void
    {
        $result = $this->resolver->resolve('Hello {{ name }}!', ['name' => 'Alice']);

        $this->assertSame('Hello Alice!', $result);
    }

    public function test_resolve_renders_templates_recursively_in_array(): void
    {
        $result = $this->resolver->resolve(
            ['greeting' => 'Hi {{ name }}', 'count' => '{{ items }} items'],
            ['name' => 'Bob', 'items' => 3],
        );

        $this->assertSame(['greeting' => 'Hi Bob', 'count' => '3 items'], $result);
    }

    public function test_resolve_passes_through_non_string_non_array_values(): void
    {
        $this->assertSame(42, $this->resolver->resolve(42, []));
        $this->assertNull($this->resolver->resolve(null, []));
        $this->assertTrue($this->resolver->resolve(true, []));
    }

    public function test_resolve_renders_dollar_brace_template_syntax(): void
    {
        $result = $this->resolver->resolve('User: ${user_name}', ['user_name' => 'Carol']);

        $this->assertSame('User: Carol', $result);
    }

    public function test_resolve_renders_nested_array_values(): void
    {
        $result = $this->resolver->resolve(
            [['label' => '{{ title }}']],
            ['title' => 'Test'],
        );

        $this->assertSame([['label' => 'Test']], $result);
    }
}
