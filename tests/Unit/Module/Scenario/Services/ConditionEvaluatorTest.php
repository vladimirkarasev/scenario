<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use Module\Scenario\Services\Nodes\Condition\ConditionEvaluator;
use Tests\TestCase;

final class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(ConditionEvaluator::class);
    }

    public function test_equals_operator_matches_exact_string(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ user_role }}',
            'rules' => [
                ['operator' => 'equals', 'value' => 'admin', 'targetNodeId' => 'node_admin'],
            ],
        ], ['user_role' => 'admin']);

        $this->assertSame('node_admin', $result);
    }

    public function test_equals_operator_does_not_match_different_string(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ user_role }}',
            'rules' => [
                ['operator' => 'equals', 'value' => 'admin', 'targetNodeId' => 'node_admin'],
            ],
            'fallbackTargetNodeId' => 'node_fallback',
        ], ['user_role' => 'manager']);

        $this->assertSame('node_fallback', $result);
    }

    public function test_not_equals_operator(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ score }}',
            'rules' => [
                ['operator' => 'not_equals', 'value' => '0', 'targetNodeId' => 'node_nonzero'],
            ],
        ], ['score' => 5]);

        $this->assertSame('node_nonzero', $result);
    }

    public function test_exists_operator_matches_truthy_value(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ user_id }}',
            'rules' => [
                ['operator' => 'exists', 'value' => null, 'targetNodeId' => 'node_logged_in'],
            ],
        ], ['user_id' => 42]);

        $this->assertSame('node_logged_in', $result);
    }

    public function test_exists_operator_does_not_match_empty_value(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ user_id }}',
            'rules' => [
                ['operator' => 'exists', 'value' => null, 'targetNodeId' => 'node_logged_in'],
            ],
            'fallbackTargetNodeId' => 'node_guest',
        ], ['user_id' => '']);

        $this->assertSame('node_guest', $result);
    }

    public function test_empty_operator_matches_blank_value(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ comment }}',
            'rules' => [
                ['operator' => 'empty', 'value' => null, 'targetNodeId' => 'node_no_comment'],
            ],
        ], ['comment' => '']);

        $this->assertSame('node_no_comment', $result);
    }

    public function test_in_operator_matches_value_in_list(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ status }}',
            'rules' => [
                ['operator' => 'in', 'value' => ['active', 'trial'], 'targetNodeId' => 'node_paying'],
            ],
        ], ['status' => 'active']);

        $this->assertSame('node_paying', $result);
    }

    public function test_in_operator_does_not_match_value_not_in_list(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ status }}',
            'rules' => [
                ['operator' => 'in', 'value' => ['active', 'trial'], 'targetNodeId' => 'node_paying'],
            ],
            'fallbackTargetNodeId' => 'node_free',
        ], ['status' => 'free']);

        $this->assertSame('node_free', $result);
    }

    public function test_not_in_operator_matches_value_outside_list(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ role }}',
            'rules' => [
                ['operator' => 'not_in', 'value' => ['admin', 'superadmin'], 'targetNodeId' => 'node_regular'],
            ],
        ], ['role' => 'user']);

        $this->assertSame('node_regular', $result);
    }

    public function test_fallback_returned_when_no_rule_matches(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ age }}',
            'rules' => [
                ['operator' => 'equals', 'value' => '18', 'targetNodeId' => 'node_adult'],
            ],
            'fallbackTargetNodeId' => 'node_other',
        ], ['age' => 25]);

        $this->assertSame('node_other', $result);
    }

    public function test_null_returned_when_no_rule_matches_and_no_fallback(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ age }}',
            'rules' => [
                ['operator' => 'equals', 'value' => '18', 'targetNodeId' => 'node_adult'],
            ],
        ], ['age' => 25]);

        $this->assertNull($result);
    }

    public function test_first_matching_rule_wins(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ score }}',
            'rules' => [
                ['operator' => 'exists', 'value' => null, 'targetNodeId' => 'node_first'],
                ['operator' => 'equals', 'value' => '10', 'targetNodeId' => 'node_second'],
            ],
        ], ['score' => 10]);

        $this->assertSame('node_first', $result);
    }

    public function test_empty_expression_skips_evaluation(): void
    {
        $result = $this->evaluator->resolveTarget([
            'rules' => [],
            'fallbackTargetNodeId' => 'node_fallback',
        ], []);

        $this->assertSame('node_fallback', $result);
    }

    public function test_non_array_rules_are_skipped_gracefully(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ x }}',
            'rules' => ['not_an_array', null, 42],
            'fallbackTargetNodeId' => 'node_fallback',
        ], ['x' => 1]);

        $this->assertSame('node_fallback', $result);
    }

    public function test_integer_value_compared_as_string_with_equals(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '{{ count }}',
            'rules' => [
                ['operator' => 'equals', 'value' => '5', 'targetNodeId' => 'node_five'],
            ],
        ], ['count' => 5]);

        $this->assertSame('node_five', $result);
    }

    #[DataProvider('numericComparisonCases')]
    public function test_numeric_comparison_operators(
        string $operator,
        int|float|string $actual,
        int|float|string $expected,
        bool $matches,
    ): void {
        $result = $this->evaluator->resolveTarget([
            'expression' => 'actual',
            'rules' => [
                ['operator' => $operator, 'value' => $expected, 'targetNodeId' => 'matched'],
            ],
            'fallbackTargetNodeId' => 'fallback',
        ], ['actual' => $actual]);

        $this->assertSame($matches ? 'matched' : 'fallback', $result);
    }

    /** @return iterable<string, array{string, int|float|string, int|float|string, bool}> */
    public static function numericComparisonCases(): iterable
    {
        yield 'greater than' => ['greater_than', 11, 10, true];
        yield 'greater than false' => ['greater_than', 10, 10, false];
        yield 'greater alias' => ['gt', '10.5', 10, true];
        yield 'greater or equal' => ['greater_or_equal', 10, '10', true];
        yield 'greater or equal alias' => ['gte', 9, 10, false];
        yield 'less than' => ['less_than', -1, 0, true];
        yield 'less alias' => ['lt', 2, 1, false];
        yield 'less or equal' => ['less_or_equal', 10, 10, true];
        yield 'less or equal alias' => ['lte', 11, 10, false];
        yield 'non numeric actual' => ['greater_than', 'ten', 1, false];
        yield 'non numeric expected' => ['less_than', 1, 'ten', false];
    }

    public function test_condition_accepts_variables_in_math_expression(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => '(price * quantity) - discount',
            'rules' => [
                ['operator' => 'greater_or_equal', 'value' => 100, 'targetNodeId' => 'large_order'],
            ],
            'fallbackTargetNodeId' => 'small_order',
        ], ['price' => 30, 'quantity' => 4, 'discount' => 15]);

        $this->assertSame('large_order', $result);
    }

    public function test_condition_accepts_boolean_expression(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => 'age >= 18 and score >= 70',
            'rules' => [
                ['operator' => 'equals', 'value' => true, 'targetNodeId' => 'accepted'],
            ],
            'fallbackTargetNodeId' => 'rejected',
        ], ['age' => 20, 'score' => 75]);

        $this->assertSame('accepted', $result);
    }

    public function test_condition_uses_flat_variables_from_block_context(): void
    {
        $result = $this->evaluator->resolveTarget([
            'expression' => 'age + bonus',
            'rules' => [
                ['operator' => 'greater_than', 'value' => 20, 'targetNodeId' => 'matched'],
            ],
            'fallbackTargetNodeId' => 'fallback',
        ], [
            '_variable_map' => [
                'age' => ['_field_type' => 'number', '_block_id' => 'profile', '_field_name' => 'age'],
                'bonus' => ['_field_type' => 'number', '_block_id' => 'profile', '_field_name' => 'bonus'],
            ],
            'profile' => ['age' => 18, 'bonus' => 5],
        ]);

        $this->assertSame('matched', $result);
    }

    #[DataProvider('logicalEdgeCases')]
    public function test_edge_target_handles_logical_values(mixed $actual, string $expectedTarget): void
    {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            [
                ['target' => 'node_else', 'data' => ['value' => 'Иначе']],
                ['target' => 'node_yes', 'data' => ['value' => 'Да']],
                ['target' => 'node_no', 'data' => ['value' => 'Нет']],
                ['target' => 'node_empty', 'data' => ['value' => 'Пусто']],
            ],
            ['actual' => $actual],
        );

        $this->assertSame($expectedTarget, $result);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function logicalEdgeCases(): iterable
    {
        yield 'boolean true' => [true, 'node_yes'];
        yield 'boolean false' => [false, 'node_no'];
        yield 'null is empty' => [null, 'node_empty'];
        yield 'empty string is empty' => ['', 'node_empty'];
        yield 'blank string is empty' => ['   ', 'node_empty'];
        yield 'unmatched value uses else' => ['unknown', 'node_else'];
    }

    public function test_logical_edges_without_else_return_null_for_unmatched_value(): void
    {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            [
                ['target' => 'node_yes', 'data' => ['value' => 'Да']],
                ['target' => 'node_no', 'data' => ['value' => 'Нет']],
                ['target' => 'node_empty', 'data' => ['value' => 'Пусто']],
            ],
            ['actual' => 'unknown'],
        );

        $this->assertNull($result);
    }

    public function test_edge_expression_uses_condition_value_and_full_context(): void
    {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ user.age }}'],
            [
                ['target' => 'node_child', 'data' => ['value' => '{{ _condition.value < adulthood }}']],
                ['target' => 'node_adult', 'data' => ['value' => '{{ _condition.value >= adulthood }}']],
                ['target' => 'node_else', 'data' => ['value' => 'Иначе']],
            ],
            [
                'user' => ['age' => 20],
                'adulthood' => 18,
            ],
        );

        $this->assertSame('node_adult', $result);
    }

    public function test_false_edge_expressions_without_else_return_null(): void
    {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ age }}'],
            [
                ['target' => 'node_child', 'data' => ['value' => '{{ _condition.value < 18 }}']],
                ['target' => 'node_senior', 'data' => ['value' => '{{ _condition.value >= 65 }}']],
            ],
            ['age' => 30],
        );

        $this->assertNull($result);
    }

    #[DataProvider('englishLogicalEdgeCases')]
    public function test_edge_target_handles_english_logical_values(
        mixed $actual,
        string $edgeValue,
    ): void {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            [
                ['target' => 'node_else', 'data' => ['value' => 'ELSE']],
                ['target' => 'node_match', 'data' => ['value' => $edgeValue]],
            ],
            ['actual' => $actual],
        );

        $this->assertSame('node_match', $result);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function englishLogicalEdgeCases(): iterable
    {
        yield 'true' => [true, 'true'];
        yield 'uppercase true' => [true, 'TRUE'];
        yield 'yes' => [true, 'yes'];
        yield 'false' => [false, 'false'];
        yield 'no' => [false, 'NO'];
        yield 'empty' => ['', 'empty'];
        yield 'null' => [null, 'NULL'];
    }

    #[DataProvider('englishFallbackEdgeCases')]
    public function test_edge_target_handles_english_fallback_values(string $fallbackValue): void
    {
        $result = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            [
                ['target' => 'node_fallback', 'data' => ['value' => $fallbackValue]],
                ['target' => 'node_other', 'data' => ['value' => 'other']],
            ],
            ['actual' => 'unknown'],
        );

        $this->assertSame('node_fallback', $result);
    }

    /** @return iterable<string, array{string}> */
    public static function englishFallbackEdgeCases(): iterable
    {
        yield 'else' => ['else'];
        yield 'uppercase else' => ['ELSE'];
    }

    public function test_default_and_arbitrary_strings_are_not_treated_as_fallback(): void
    {
        $edges = [
            ['target' => 'node_default', 'data' => ['value' => 'default']],
            ['target' => 'node_custom', 'data' => ['value' => 'custom']],
        ];

        $matched = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            $edges,
            ['actual' => 'default'],
        );
        $unmatched = $this->evaluator->resolveEdgeTarget(
            ['value' => '{{ actual }}'],
            $edges,
            ['actual' => 'unknown'],
        );

        $this->assertSame('node_default', $matched);
        $this->assertNull($unmatched);
    }
}
