<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Condition;

use App\Services\Expression\ExpressionValue;
use Illuminate\Support\Str;
use Module\Scenario\Enums\ConditionEdgeMatch;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class ConditionEvaluator
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    public function resolveTarget(array $condition, array $context = []): ?string
    {
        $expression = $condition['expression'] ?? null;
        $expressionStr = is_string($expression) ? $expression : '';
        $value = $expressionStr !== '' ? $this->variableResolver->resolveValue($expressionStr, $context) : null;

        $rawRules = $condition['rules'] ?? null;
        $rules = is_array($rawRules) ? $rawRules : [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $rawOperator = $rule['operator'] ?? 'equals';
            $operator = is_string($rawOperator) ? $rawOperator : 'equals';

            if ($this->matches($value, $operator, $rule['value'] ?? null)) {
                $target = $rule['targetNodeId'] ?? null;

                return is_string($target) ? $target : null;
            }
        }

        $fallback = $condition['fallbackTargetNodeId'] ?? null;

        return is_string($fallback) ? $fallback : null;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, mixed>  $context
     */
    public function resolveEdgeTarget(array $condition, array $edges, array $context = []): ?string
    {
        $expression = $condition['value'] ?? null;

        if (!is_string($expression) || trim($expression) === '') {
            return null;
        }

        $actual = $this->variableResolver->resolveValue($expression, $context);
        $branchContext = [
            ...$context,
            ScenarioContextKey::Condition->value => ['value' => $actual],
        ];
        $fallbackTarget = null;

        foreach ($edges as $edge) {
            $data = $edge['data'] ?? null;
            $expected = is_array($data) ? ($data['value'] ?? null) : null;

            if (!is_scalar($expected) || trim((string)$expected) === '') {
                continue;
            }

            $expectedValue = trim((string)$expected);
            $target = $edge['target'] ?? null;

            if (!is_string($target) || $target === '') {
                continue;
            }

            $edgeMatch = $this->classifyEdgeValue($actual, $expectedValue, $branchContext);

            if ($edgeMatch === ConditionEdgeMatch::Fallback) {
                $fallbackTarget ??= $target;

                continue;
            }

            if ($edgeMatch === ConditionEdgeMatch::Miss) {
                continue;
            }

            return $target;
        }

        return $fallbackTarget;
    }

    /** @param array<string, mixed> $context */
    private function classifyEdgeValue(mixed $actual, string $expected, array $context): ConditionEdgeMatch
    {
        if ($this->variableResolver->isExpression($expected)) {
            return $this->variableResolver->resolveValue($expected, $context) === true
                ? ConditionEdgeMatch::Match
                : ConditionEdgeMatch::Miss;
        }

        return match (Str::lower($expected)) {
            'иначе', 'else' => ConditionEdgeMatch::Fallback,
            'да', 'true', 'yes' => $actual === true ? ConditionEdgeMatch::Match : ConditionEdgeMatch::Miss,
            'нет', 'false', 'no' => $actual === false ? ConditionEdgeMatch::Match : ConditionEdgeMatch::Miss,
            'пусто', 'empty', 'null' => $this->isBlankValue($actual) ? ConditionEdgeMatch::Match : ConditionEdgeMatch::Miss,
            default => $this->matches($actual, 'equals', $expected) ? ConditionEdgeMatch::Match : ConditionEdgeMatch::Miss,
        };
    }

    private function matches(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'exists' => !$this->isBlankValue($actual),
            'empty' => $this->isBlankValue($actual),
            'equals' => $this->toStr($actual) === $this->toStr($expected),
            'not_equals' => $this->toStr($actual) !== $this->toStr($expected),
            'greater_than', 'gt' => $this->compareNumbers($actual, $expected, static fn(float $a, float $b): bool => $a > $b),
            'greater_or_equal', 'gte' => $this->compareNumbers($actual, $expected, static fn(float $a, float $b): bool => $a >= $b),
            'less_than', 'lt' => $this->compareNumbers($actual, $expected, static fn(float $a, float $b): bool => $a < $b),
            'less_or_equal', 'lte' => $this->compareNumbers($actual, $expected, static fn(float $a, float $b): bool => $a <= $b),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && !in_array($actual, $expected, true),
            default => false,
        };
    }

    private function isBlankValue(mixed $value): bool
    {
        if ($value instanceof ExpressionValue) {
            return blank($value->jsonSerialize());
        }

        return blank($value);
    }

    /** @param callable(float, float): bool $comparator */
    private function compareNumbers(mixed $actual, mixed $expected, callable $comparator): bool
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return false;
        }

        return $comparator((float)$actual, (float)$expected);
    }

    private function toStr(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
