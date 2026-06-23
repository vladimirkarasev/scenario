# Добавление field shape

Ты — senior fullstack разработчик. Добавь структурированный shape для значения поля сценария по паттерну, принятому в проекте (PhoneShape-style).

## Что добавить

$ARGUMENTS

Если аргументы не переданы — спроси:
1. Какое поле (`phone`, `directory_list`, `select`, новый тип, …)?
2. Какие поля у shape (например, `{country, formatted, original}`)?
3. Какое поле shape считается "default-отображением" в `{{ Var }}` (например, `formatted`)?

---

## Контекст

Когда поле формы должно хранить не одну скалярную строку, а структурированный объект — используется паттерн **Shape**. Параллельные определения на PHP и TS, единая форма данных. Готовый пример: `PhoneShape` (`module/Scenario/Support/PhoneShape.php` + `resources/js/lib/phone-shape.ts`).

## Слои

### 1. Backend — `module/Scenario/Support/XxxShape.php`

`final readonly class` со static-методами:

```php
<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

/**
 * @phpstan-type XxxArray array{field1: string, field2: string, ...}
 */
final readonly class XxxShape
{
    /** @phpstan-assert-if-true XxxArray $value */
    public static function matches(mixed $value): bool
    {
        return is_array($value)
            && isset($value['field1'], $value['field2'])
            && is_string($value['field1'])
            && is_string($value['field2']);
    }

    public static function field1(mixed $value): ?string
    {
        return self::matches($value) ? $value['field1'] : null;
    }
    // accessors для каждого поля
}
```

PHPDoc `@phpstan-assert-if-true` критична — PHPStan через неё сужает тип после `matches()`, и `$value['field']` становится валидным без дополнительных проверок.

### 2. Frontend — `resources/js/lib/xxx-shape.ts`

```ts
export interface XxxShape {
    field1: string
    field2: string
}

export function isXxxShape(value: unknown): value is XxxShape {
    if (!value || typeof value !== 'object') return false
    const v = value as Record<string, unknown>
    return typeof v.field1 === 'string' && typeof v.field2 === 'string'
}

export function field1(value: unknown): string | null {
    return isXxxShape(value) ? value.field1 : null
}
// accessors
```

### 3. Input-компонент

Компонент принимает `XxxShape | string | null` (string — для backward compat со старыми данными), эмитит `XxxShape | null`. Внутри держит UI-state, при `emit('update:modelValue', ...)` собирает shape.

См. `resources/js/components/ui/phone-input/PhoneInput.vue` как референс — там `toFormattedString()` нормализует и legacy-строку, и новый объект.

### 4. `resources/js/modules/scenario/components/SurveyBlockRenderer.vue`

В ветке для типа — пробросить объект без `String(...)` каста:

```vue
<XxxInput
    :model-value="(formData[fieldName] as XxxShape | string | null) ?? null"
    :disabled="disabled"
    :error="hasError"
    @update:model-value="formData[fieldName] = $event"
/>
```

Импорт типа: `import XxxInput, { type XxxValue } from '@/components/ui/xxx-input/XxxInput.vue'` или из shared `@/lib/xxx-shape`.

### 5. `useBlockForm.ts` — schema-ветка

```ts
if (type === 'xxx') {
    const shape = z.object({
        field1: z.string(),
        field2: z.string(),
    })
    if (required) {
        return z.union([
            z.string().min(1, 'Поле обязательно для заполнения'),  // legacy
            shape.refine((v) => v.field1.length > 0, 'Поле обязательно для заполнения'),
        ])
    }
    return z.union([z.string(), shape]).or(z.literal('')).nullable().optional()
}
```

Required-вариант проверяет непустоту через `refine` (например, `original.length > 0` для phone). Для optional — union с `string` (legacy) + nullable + optional.

### 6. `module/Scenario/Services/ScenarioExpressionService::stringify`

В ветках `ScenarioExpressionValue` и `is_array($value)` — после обычных проверок добавить:

```php
if (XxxShape::matches($arr)) {
    return $arr['defaultDisplayField'];
}
```

Это даёт `{{ Var }}` → отображаемое представление. Path access (`{{ Var.field1 }}`) работает автоматически — ExpressionLanguage видит ассоциативный массив, обёрнутый в `ScenarioExpressionValue`, и обращается через `offsetGet`.

### 7. `BlockNodeHandler.php` — defaultValue в props

Обычно ничего менять не нужно: `defaultValue` пробрасывается из контекста как-есть (объект или legacy-строка), input-компонент сам разбирается через `toFormattedString` / аналог.

## Чеклист по PR

1. [ ] `module/Scenario/Support/XxxShape.php` — `matches()` + accessors
2. [ ] `resources/js/lib/xxx-shape.ts` — `interface` + `isXxxShape()` + accessors
3. [ ] Input-компонент: принимает `XxxShape | legacy | null`, эмитит `XxxShape | null`
4. [ ] `SurveyBlockRenderer.vue` — пробросить объект без каста, импортировать тип
5. [ ] `useBlockForm.ts` — schema-ветка с union(string, shape) и backward compat
6. [ ] `ScenarioExpressionService::stringify` — `XxxShape::matches()` в обеих ветках (`ScenarioExpressionValue` и `is_array`)
7. [ ] `./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M --no-progress` чист
8. [ ] `npx vue-tsc --noEmit` чист
9. [ ] После деплоя — `task artisan -- octane:reload` (бэк-инстанс в воркере держит старую логику)

## Backward compat (важно)

Хранилище в БД содержит старые скалярные значения. **Все слои должны принимать `string | XxxShape | null` на вход.** Stringify в `is_string` ветке отдаёт строку как есть — для phone это formatted (legacy хранил уже отформатированную). При выборе default-поля shape учитывай, что для legacy его не будет — нужна корректная деградация.

## Готовый пример

`PhoneShape` — phone field:
- `module/Scenario/Support/PhoneShape.php`
- `resources/js/lib/phone-shape.ts`
- `resources/js/components/ui/phone-input/PhoneInput.vue`
- Подключения в `SurveyBlockRenderer.vue` / `useBlockForm.ts` / `ScenarioExpressionService.php` (см. ветку `phone` и вызовы `PhoneShape::matches`).
