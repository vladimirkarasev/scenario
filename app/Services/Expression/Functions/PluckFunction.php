<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;

final readonly class PluckFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'pluck';
    }

    /** @return list<mixed> */
    public function evaluate(array $context, mixed ...$args): array
    {
        $items = $args[0] ?? null;
        $path = $args[1] ?? null;

        if (!is_string($path) || $path === '') {
            return [];
        }

        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items);
        } elseif (is_object($items)) {
            $items = [$items];
        } elseif (is_array($items)) {
            if (!array_is_list($items)) {
                $items = [$items];
            }
        } else {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = data_get($item, $path);
        }

        return $result;
    }
}
