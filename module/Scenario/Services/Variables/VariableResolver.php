<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables;

use App\Services\Expression\ExpressionService;
use Module\Scenario\DTO\Variables\SelectVariableEntry;
use Module\Scenario\DTO\Variables\VariableEntryFactory;
use Module\Scenario\DTO\Variables\VariableEntryInterface;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Support\SelectShape;
use Throwable;

final readonly class VariableResolver
{
    public function __construct(
        private ExpressionService $expressionService,
    ) {
    }

    /** @param  array<string, mixed>  $context */
    public function resolveValue(string $expression, array $context = []): mixed
    {
        return $this->expressionService->evaluate($expression, $this->prepareContext($context));
    }

    public function isExpression(string $value): bool
    {
        return $this->expressionService->isWrappedExpression($value);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(mixed $value, array $context = []): mixed
    {
        return $this->expressionService->render($value, $this->prepareContext($context), true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function flatten(array $context): array
    {
        return $this->prepareContext($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function prepareContext(array $context): array
    {
        /** @var array<string, array<string, mixed>> $variableMap */
        $variableMap = is_array(
            $context[ScenarioContextKey::VariableMap->value] ?? null
        ) ? $context[ScenarioContextKey::VariableMap->value] : [];

        return $this->injectFlatVariables($context, $variableMap);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $variableMap
     * @return array<string, mixed>
     */
    private function injectFlatVariables(array $context, array $variableMap): array
    {
        foreach ($variableMap as $varName => $rawEntry) {
            if (isset($context[$varName])) {
                continue;
            }

            try {
                $entry = VariableEntryFactory::fromArray($rawEntry);
            } catch (Throwable) {
                continue;
            }

            $blockData = is_array($context[$entry->blockId()] ?? null) ? $context[$entry->blockId()] : [];
            $rawValue = $blockData[$entry->fieldName()] ?? null;
            $context[$varName] = $this->resolveFlatValue($rawValue, $entry);
        }

        return $context;
    }

    private function resolveFlatValue(mixed $rawValue, VariableEntryInterface $entry): mixed
    {
        if (!$entry instanceof SelectVariableEntry) {
            return $rawValue;
        }

        if (SelectShape::matches($rawValue)) {
            return $rawValue;
        }

        if (is_array($rawValue) && array_is_list($rawValue) && $this->isListOfSelectShapes($rawValue)) {
            return $rawValue;
        }

        return $this->selectLabels($rawValue, $entry);
    }

    /** @param  list<mixed>  $value */
    private function isListOfSelectShapes(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_all($value, fn($item) => SelectShape::matches($item));
    }

    private function selectLabels(mixed $rawValue, SelectVariableEntry $entry): mixed
    {
        $options = $entry->options();

        if ($options === []) {
            return $rawValue;
        }

        $labelMap = $this->buildLabelMap($options);

        if (is_array($rawValue)) {
            return array_values(
                array_map(
                    static function (mixed $value) use ($labelMap): string {
                        $key = is_string($value) ? $value : (is_scalar($value) ? (string)$value : '');

                        return $labelMap[$key] ?? $key;
                    },
                    $rawValue,
                )
            );
        }

        if (!is_string($rawValue)) {
            return $rawValue;
        }

        return array_key_exists($rawValue, $labelMap) ? $labelMap[$rawValue] : $rawValue;
    }

    /**
     * @param  list<array<mixed>>  $options
     * @return array<string, string>
     */
    private function buildLabelMap(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            $rawValue = $option['value'] ?? null;
            $rawLabel = $option['label'] ?? null;
            $value = is_string($rawValue) ? $rawValue : (is_scalar($rawValue) ? (string)$rawValue : null);
            $label = is_string($rawLabel) ? $rawLabel : '';

            if ($value !== null && $value !== '') {
                $map[$value] = $label;
            }
        }

        return $map;
    }
}
