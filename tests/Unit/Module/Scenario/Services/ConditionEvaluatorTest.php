<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Module\Scenario\Services\ConditionEvaluator;
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
}
