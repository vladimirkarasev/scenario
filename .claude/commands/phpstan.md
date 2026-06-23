# PHPStan / Larastan (Laravel 13 + PHP 8.5)

Ты — senior PHP разработчик. Запусти статический анализ через Larastan и исправь найденные ошибки.

## Цель

$ARGUMENTS

Если аргументы не переданы — анализируй весь проект и исправляй все ошибки.

---

## Шаг 1 — Проверь установку

```bash
composer show larastan/larastan 2>/dev/null || echo "NOT_INSTALLED"
```

Если **NOT_INSTALLED** — установи:

```bash
composer require --dev larastan/larastan
```

---

## Шаг 2 — Проверь/создай конфиг

Найди `phpstan.neon` или `phpstan.neon.dist` в корне проекта. Если нет — создай `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - module
    level: 5
    checkMissingIterableValueType: false
    ignoreErrors:
        - '#Unsafe usage of new static#'
```

**Уровни:** начинай с того что уже стоит. Если конфига нет — стартуй с `level: 5`.

---

## Шаг 3 — Запусти анализ

```bash
./vendor/bin/phpstan analyse --memory-limit=512M --error-format=table 2>&1
```

Если нужно анализировать конкретный путь:
```bash
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M 2>&1
```

---

## Шаг 4 — Исправь ошибки

Читай каждую ошибку и исправляй в коде. Приоритеты:

### Типизация (исправляй всегда)

```php
// ❌ было
public function handle($data) { ... }

// ✅ стало
public function handle(ScenarioRunData $data): array { ... }
```

### Nullable без проверки

```php
// ❌ было
$run->scenario->name  // scenario может быть null

// ✅ стало
$run->scenario?->name
// или загружай с проверкой
$run->loadMissing('scenario');
$scenario = $run->scenario ?? throw new \LogicException('...');
```

### Mixed-тип из массива

```php
// ❌ было
$value = $array['key'];  // mixed

// ✅ стало  
$value = (string) ($array['key'] ?? '');
// или используй data_get с явным типом
/** @var string $value */
$value = data_get($array, 'key', '');
```

### Метод не существует на union-типе

```php
// ❌ было
$model->relationMethod()  // может вернуть Collection|Model

// ✅ стало
$model->relationMethod()->first()
// или добавь @return в relationship
```

### Неиспользуемые переменные / dead code

```php
// Удаляй, не комментируй
```

### Ошибки Eloquent / отношений

Добавляй PHPDoc к relationship-методам:

```php
/** @return \Illuminate\Database\Eloquent\Relations\HasMany<ScenarioVersion> */
public function versions(): HasMany
{
    return $this->hasMany(ScenarioVersion::class);
}
```

### Когда допустим `@phpstan-ignore`

Используй точечно, только если исправление сломает логику или это known Larastan limitation:

```php
/** @phpstan-ignore-next-line */
$result = SomeClass::magicStaticCall();
```

Никогда не игнорируй целые файлы или директории через `ignoreErrors` в neon без причины.

---

## Шаг 5 — Проверь результат

```bash
./vendor/bin/phpstan analyse --memory-limit=512M 2>&1 | tail -5
```

Должно быть `[OK] No errors`.

---

## Формат ответа

1. **Версия Larastan и уровень** — что установлено / какой level
2. **Список ошибок** — кратко по файлам
3. **Что исправил** — для каждого исправления: ПОЧЕМУ, а не ЧТО
4. **Что не стал исправлять** — если оставил `@phpstan-ignore`, объясни почему
5. Применяй изменения через Edit, потом снова запусти анализ

## Важно

- Не понижай level чтобы скрыть ошибки
- Не добавляй `mixed` как тип — это капитуляция
- Не игнорируй ошибки в `ignoreErrors` без комментария почему
- PHP 8.3: используй `readonly`, `match`, union types, `never` — они помогают PHPStan