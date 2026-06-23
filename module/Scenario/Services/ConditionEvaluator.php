<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

final readonly class ConditionEvaluator
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    /**
     * Вычисляет выражение из условия и возвращает ID целевого узла.
     *
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    public function resolveTarget(array $condition, array $context = []): ?string
    {
        $expression = $condition['expression'] ?? null;
        $expressionStr = is_string($expression) ? $expression : '';
        $value = $expressionStr !== '' ? $this->variableResolver->resolveValue($expressionStr, $context) : null;

        $rawRules = $condition['rules'] ?? null;
        $rules = is_array($rawRules) ? $rawRules : [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
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

    private function matches(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'exists' => filled($actual),
            'empty' => blank($actual),
            'equals' => $this->toStr($actual) === $this->toStr($expected),
            'not_equals' => $this->toStr($actual) !== $this->toStr($expected),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            default => false,
        };
    }

    /** Приводит скалярное значение к строке для сравнения. */
    private function toStr(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
