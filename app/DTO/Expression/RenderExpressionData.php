<?php

declare(strict_types=1);

namespace App\DTO\Expression;

use App\Http\Requests\Expression\RenderExpressionRequest;

final readonly class RenderExpressionData
{
    public function __construct(
        public string $template,
        public ExpressionContextData $context,
    ) {
    }

    public static function fromRequest(RenderExpressionRequest $request): self
    {
        $template = $request->validated('template');
        $context = $request->validated('context', []);

        return new self(
            template: is_string($template) ? $template : '',
            context: ExpressionContextData::from($context),
        );
    }
}
