<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;

/**
 * Извлекает поле из каждого элемента массива/коллекции.
 *
 *   {{ pluck(Справочник, "label") }}            -> ["Один", "Два"]
 *   {{ pluck(Справочник, "data.nazvanie") }}    -> поддержка точечной нотации
 *   {{ implode(",", pluck(Справочник, "id")) }} -> "1,2,3"
 */
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

        // Контекст ExpressionService оборачивает assoc-массивы в ExpressionValue
        // (ArrayAccess). Single-mode справочник в этом случае — один объект, multiple —
        // массив таких объектов. Приводим к списку, чтобы итерация была единой.
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
            // data_get умеет ходить через массивы, объекты и ArrayAccess (ExpressionValue),
            // поддерживает точечную нотацию: "data.gorod".
            $result[] = data_get($item, $path);
        }

        return $result;
    }
}
