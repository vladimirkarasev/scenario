<?php

declare(strict_types=1);

namespace App\DTO\Expression;

use App\Http\Requests\Expression\RenderExpressionBatchRequest;

final readonly class RenderExpressionBatchData
{
    /** @param list<RenderExpressionBatchContextData> $contexts */
    public function __construct(
        public string $template,
        public array $contexts,
    ) {
    }

    public static function fromRequest(RenderExpressionBatchRequest $request): self
    {
        $contexts = [];
        $rawContexts = $request->validated('context', []);

        if (is_array($rawContexts)) {
            foreach ($rawContexts as $rawContext) {
                if (! is_array($rawContext)) {
                    continue;
                }

                $id = $rawContext['id'] ?? null;
                if (is_int($id) || is_string($id)) {
                    $contexts[] = new RenderExpressionBatchContextData(
                        id: $id,
                        context: ExpressionContextData::from($rawContext['data'] ?? []),
                    );
                }
            }
        }

        $template = $request->validated('item.template', '');

        return new self(
            template: is_string($template) ? $template : '',
            contexts: $contexts,
        );
    }
}
