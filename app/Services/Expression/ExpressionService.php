<?php

declare(strict_types=1);

namespace App\Services\Expression;

use App\Services\Expression\Functions\AddTimeFunction;
use App\Services\Expression\Functions\ConcatFunction;
use App\Services\Expression\Functions\DateFormatFunction;
use App\Services\Expression\Functions\ImplodeFunction;
use App\Services\Expression\Functions\PluckFunction;
use App\Services\Expression\Functions\LengthFunction;
use App\Services\Expression\Functions\LowerFunction;
use App\Services\Expression\Functions\ReplaceFunction;
use App\Services\Expression\Functions\TrimFunction;
use App\Services\Expression\Functions\UpperFunction;
use Generator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final readonly class ExpressionService
{
    private const string TEMPLATE_PATTERN = '/\{\{\s*(.+?)\s*\}\}|\$\{\s*(.+?)\s*\}/';

    private const array WRAPPED_EXPRESSION_PATTERNS = [
        '/^\{\{\s*(.+?)\s*\}\}$/',
        '/^\$\{\s*(.+?)\s*\}$/',
    ];

    private const array RESERVED_NAMES = [
        'and',
        'false',
        'not',
        'null',
        'or',
        'true',
    ];

    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage(
            new FilesystemAdapter(
                namespace: 'application_expression_language',
                directory: storage_path('framework/cache/expression-language'),
            ),
        );

        $this->registerFunctions();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(string $expression, array $context = []): mixed
    {
        $expression = $this->unwrapExpression($expression);

        return $this->expressionLanguage->evaluate(
            $expression,
            $this->normalizeContext($context, $expression),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(mixed $value, array $context = [], bool $emptyExpressionValueAsBlank = false): mixed
    {
        if (is_array($value)) {
            return array_map(
                fn(mixed $item): mixed => $this->render($item, $context, $emptyExpressionValueAsBlank),
                $value,
            );
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->renderString($value, $context, $emptyExpressionValueAsBlank);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderString(
        string $template,
        array $context = [],
        bool $emptyExpressionValueAsBlank = false
    ): string {
        return preg_replace_callback(
            self::TEMPLATE_PATTERN,
            function (array $matches) use ($context, $emptyExpressionValueAsBlank): string {
                $inner = trim((string)($matches[1] ?: ($matches[2] ?? '')));

                // Спец-суффикс ._array — вернуть значение как JSON-массив (для multiple-полей).
                if (preg_match('/^(.+)\._array$/u', $inner, $m) === 1) {
                    return $this->renderAsArray(trim($m[1]), $context, $emptyExpressionValueAsBlank);
                }

                try {
                    return $this->stringify($this->evaluate($inner, $context), $emptyExpressionValueAsBlank);
                } catch (\Throwable) {
                    // Доступ к свойству null / массива (не выбрано / multiple) не должен валить
                    // весь рендер — выражение даёт пустую строку.
                    return '';
                }
            },
            $template,
        ) ?? $template;
    }

    private function registerFunctions(): void
    {
        $notCompilable = static fn(): string => throw new \LogicException(
            'compile() is not supported for application expressions',
        );

        foreach ($this->functions() as $function) {
            $this->expressionLanguage->register(
                $function->name(),
                $notCompilable,
                static fn(array $context, mixed ...$args): mixed => $function->evaluate($context, ...$args),
            );
        }
    }

    /** @return Generator<ExpressionFunctionInterface> */
    private function functions(): Generator
    {
        yield new UpperFunction;
        yield new LowerFunction;
        yield new TrimFunction;
        yield new LengthFunction;
        yield new ConcatFunction;
        yield new ReplaceFunction;
        yield new ImplodeFunction;
        yield new PluckFunction;
        yield new DateFormatFunction;
        yield new AddTimeFunction;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderAsArray(string $expression, array $context, bool $emptyExpressionValueAsBlank): string
    {
        $value = $this->evaluate($expression, $context);

        if ($value instanceof ExpressionValue) {
            $value = $value->jsonSerialize();
        }

        if (!is_array($value)) {
            return $this->stringify($value, $emptyExpressionValueAsBlank);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function unwrapExpression(string $expression): string
    {
        $expression = trim($expression);

        foreach (self::WRAPPED_EXPRESSION_PATTERNS as $pattern) {
            if (preg_match($pattern, $expression, $matches) === 1) {
                return trim((string)$matches[1]);
            }
        }

        return $expression;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context, string $expression): array
    {
        $context = array_map(
            $this->normalizeValue(...),
            $context,
        );

        foreach ($this->extractVariableNames($expression) as $name) {
            $context[$name] ??= new ExpressionValue([]);
        }

        return $context;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                $this->normalizeValue(...),
                $value,
            );
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string)$key] = $this->normalizeValue($item);
        }

        return new ExpressionValue($normalized);
    }

    /**
     * Если массив — это список shapes (DirectoryList или Select) с полем label,
     * вернёт массив лейблов. Иначе null.
     *
     * @param  list<mixed>  $value
     * @return list<string>|null
     */
    private function collectShapeLabels(array $value): ?array
    {
        $labels = [];
        foreach ($value as $item) {
            if ($item instanceof ExpressionValue) {
                $item = $item->jsonSerialize();
            }

            if ($this->matchesDirectoryListShape($item)) {
                $labels[] = $item['label'];

                continue;
            }
            if ($this->matchesSelectShape($item)) {
                $labels[] = $item['label'];

                continue;
            }

            return null;
        }

        return $labels;
    }

    private function stringify(mixed $value, bool $emptyExpressionValueAsBlank = false): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof ExpressionValue) {
            $arr = $value->jsonSerialize();
            if ($this->matchesPhoneShape($arr)) {
                return $arr['formatted'];
            }
            if ($this->matchesDirectoryListShape($arr)) {
                return $arr['label'];
            }
            if ($this->matchesSelectShape($arr)) {
                return $arr['label'];
            }

            if ($arr === [] && $emptyExpressionValueAsBlank) {
                return '';
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_array($value)) {
            if ($this->matchesPhoneShape($value)) {
                return $value['formatted'];
            }
            if ($this->matchesDirectoryListShape($value)) {
                return $value['label'];
            }
            if ($this->matchesSelectShape($value)) {
                return $value['label'];
            }

            if ($value === []) {
                return '';
            }

            // Массив shapes (multiple для DirectoryList / Select) — джойним лейблы
            if (array_is_list($value)) {
                $labels = $this->collectShapeLabels($value);
                if ($labels !== null) {
                    return implode(', ', $labels);
                }
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function extractVariableNames(string $expression): array
    {
        preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', $expression, $matches);

        return array_filter(
                $matches[0],
                fn(string $name): bool => !in_array($name, self::RESERVED_NAMES, true),
            )
                |> array_unique(...)
                |> array_values(...);
    }

    /**
     * @phpstan-assert-if-true array{country: string, formatted: string, original: string} $value
     */
    private function matchesPhoneShape(mixed $value): bool
    {
        return is_array($value)
            && isset($value['country'], $value['formatted'], $value['original'])
            && is_string($value['country'])
            && is_string($value['formatted'])
            && is_string($value['original']);
    }

    /**
     * @phpstan-assert-if-true array{id: string, label: string, data: array<string, mixed>, parent_id: string|null, external_key: string} $value
     */
    private function matchesDirectoryListShape(mixed $value): bool
    {
        return is_array($value)
            && isset($value['id'], $value['label'], $value['data'], $value['external_key'])
            && array_key_exists('parent_id', $value)
            && is_string($value['id'])
            && is_string($value['label'])
            && is_array($value['data'])
            && is_string($value['external_key'])
            && ($value['parent_id'] === null || is_string($value['parent_id']));
    }

    /**
     * @phpstan-assert-if-true array{value: string, label: string} $value
     */
    private function matchesSelectShape(mixed $value): bool
    {
        return is_array($value)
            && isset($value['value'], $value['label'])
            && is_string($value['value'])
            && is_string($value['label']);
    }
}
