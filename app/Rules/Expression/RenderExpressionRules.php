<?php

declare(strict_types=1);

namespace App\Rules\Expression;

final readonly class RenderExpressionRules
{
    /** @return array<string, list<mixed>> */
    public static function get(): array
    {
        return [
            'template' => ['required', 'string', 'min:1', 'max:5000', new ExpressionTemplateRule],
            'context' => ['sometimes', 'array', 'max:100', new ExpressionContextRule],
        ];
    }
}
