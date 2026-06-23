---
name: add-expression-function
description: Add a new function for ScenarioExpressionService — create one class implementing ExpressionFunctionInterface in module/Scenario/Services/Expression/Functions/ and yield it from ScenarioExpressionService::functions(). Apply when asked to add a new {{ fn(...) }} helper for scenarios (date math, string transform, lookup, etc.).
---

# Add Expression Function

Функции для `{{ ... }}` в сценариях живут в `module/Scenario/Services/Expression/Functions/`. Каждая — отдельный класс. Сервис итерирует генератор `functions()` и регистрирует функции на `ExpressionLanguage`.

## Структура

```
module/Scenario/Services/Expression/
  ScenarioExpressionService.php     ← сервис, итерирует functions(), регистрирует
  ExpressionFunctionInterface.php   ← контракт: name() + evaluate(array $context, mixed ...$args)
  Functions/
    DateFormatFunction.php
    AddTimeFunction.php
    UpperFunction.php
    ConcatFunction.php
    … каждая функция = отдельный класс …
  Support/
    Scalar.php
    CarbonParser.php
    MomentFormat.php
    Duration.php
```

## Step 1 — Класс функции

```php
// module/Scenario/Services/Expression/Functions/FooFunction.php
namespace Module\Scenario\Services\Expression\Functions;

use Module\Scenario\Services\Expression\ExpressionFunctionInterface;

final readonly class FooFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'foo';
    }

    public function evaluate(array $context, mixed ...$args): mixed
    {
        // shared-логика — в Support/, не дублировать
        $input = $args[0] ?? '';
        return /* ... */;
    }
}
```

Правила:

- `final readonly` (PHP 8.4).
- Реализует `ExpressionFunctionInterface` (`name()` + `evaluate()`).
- Shared-логика (парсинг даты, форматирование, дюрейшны) — в `Support/` (`Scalar`, `CarbonParser`, `MomentFormat`, `Duration`).
- Не лезть в `$context` без причины — большинство функций берут только `$args`.

## Step 2 — yield в генераторе

```php
// module/Scenario/Services/Expression/ScenarioExpressionService.php

protected function functions(): \Generator
{
    yield new DateFormatFunction();
    yield new AddTimeFunction();
    yield new UpperFunction();
    yield new ConcatFunction();
    yield new FooFunction();   // ← добавил
}
```

## Step 3 — Тест без бутстрапа Laravel

```php
test('foo: возвращает X для входа Y', function () {
    $fn = new FooFunction();
    expect($fn->evaluate([], 'input'))->toBe('expected');
});
```

Тесты на функцию пишутся **без** загрузки Laravel-контейнера — функция чистая, делает `($context, ...$args) → mixed`.

## Почему так

Раньше `registerFunctions` в `ScenarioExpressionService` был ~100 строк с лямбдами — нечитаемо и невозможно тестить отдельно. Распилили на классы + `yield`-генератор `functions()`. Решение принял пользователь («список функций через yield»).

## Важно про обратную совместимость

Старый прокси-синтаксис (`Дата.year`, `.iso`, `.format:...`, `.keys`, `.list`) **удалён** — всё через функции. Если придётся реанимировать — сначала проверить, не лежит ли в БД старый синтаксис в `nodes_json` / `schema_json`. См. [[scenario-variables]].

## Связанные

- [[scenario-variables]] — обзор системы переменных, plain-имена, ExpressionLanguage.
- `module/Scenario/Services/Expression/Support/` — переиспользуемые утилиты для функций.
