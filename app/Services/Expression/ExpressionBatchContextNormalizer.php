<?php

declare(strict_types=1);

namespace App\Services\Expression;

use App\DTO\Expression\ExpressionContextData;
use App\DTO\Expression\RenderExpressionBatchContextData;

final readonly class ExpressionBatchContextNormalizer
{
    /**
     * @param  list<RenderExpressionBatchContextData>  $contexts
     * @return list<RenderExpressionBatchContextData>
     */
    public function normalize(array $contexts): array
    {
        $defaults = [];
        foreach ($contexts as $context) {
            foreach (array_keys($context->context->values) as $key) {
                $defaults[$key] = null;
            }
        }

        return array_map(
            static function (RenderExpressionBatchContextData $context) use ($defaults): RenderExpressionBatchContextData {
                $values = array_replace($defaults, $context->context->values);
                $values['item'] = $context->context->values;

                return new RenderExpressionBatchContextData(
                    id: $context->id,
                    context: new ExpressionContextData($values),
                );
            },
            $contexts,
        );
    }
}
