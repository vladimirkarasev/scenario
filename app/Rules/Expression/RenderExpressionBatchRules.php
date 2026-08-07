<?php

declare(strict_types=1);

namespace App\Rules\Expression;

final readonly class RenderExpressionBatchRules
{
    /** @return array<string, list<mixed>> */
    public static function get(): array
    {
        return [
            'item' => ['required', 'array'],
            'item.template' => ['required', 'string', 'min:1', 'max:5000', new ExpressionTemplateRule],
            'context' => ['required', 'array', 'min:1', 'max:100', new ExpressionBatchRule],
            'context.*' => ['required', 'array'],
            'context.*.id' => ['required', 'distinct', new ExpressionContextIdRule],
            'context.*.data' => ['required', 'array', 'max:100', new ExpressionContextRule],
        ];
    }
}
