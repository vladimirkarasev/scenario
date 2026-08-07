---
name: pre-mr
description: Checklist and steps to run before opening a Merge Request — tests, static analysis, code review, migration notes, MR description. Use when asked to prepare a branch for MR, review changes before pushing, or write an MR description.
---

# Before Opening an MR

## Quick summary

```
1. Тесты → 2. PHPStan → 3. Rector dry-run → 4. TypeScript → 5. ESLint → 6. Self-review → 7. MR description
```

Порядок важен: после применения Rector повторно запускай PHPStan.

---

## 1. Тесты

```bash
task test

# Только конкретный файл/класс
task test:unit -- --filter=UserGroupsControllerTest

# Параллельно (быстрее на большом наборе)
task test -- --parallel
```

Тесты используют SQLite in-memory (`DB_DATABASE=:memory:`), `RefreshDatabase`, без фабрик — модели создаются через `Model::query()->create([...])`.

**Если тест падает:**
- Убедись что новая миграция добавлена в `database/migrations/`
- Если добавил новый `Permission` или `Role` — проверь, нужен ли seeder в тесте (см. `PermissionSeeder`)
- Если тест проверяет HTTP — убедись что роут зарегистрирован в `routes/api.php` модуля

---

## 2. PHPStan (backend)

```bash
# Только затронутый модуль (быстрее)
task phpstan -- module/<Module>

# Несколько модулей
task phpstan -- module/Foo module/Bar

# Полный прогон (app/ + все module/)
task phpstan
```

Уровень: **10**. Ноль ошибок обязателен.

Предупреждения вида `ignored error pattern was not matched` при анализе подпапки — ожидаемо, не ошибка (глобальные паттерны для Larastan-ограничений не совпадают на маленьком подмножестве кода).

---

## 3. Rector

```bash
# Проверить без изменений
task rector:check -- module/<Module>

# Применить после просмотра предложенных изменений
task rector -- module/<Module>
```

После Rector запусти PHPStan ещё раз.

---

## 4. TypeScript (frontend)

```bash
task typecheck
```

Строгий режим, охватывает `.ts` и `.vue` файлы. Ноль ошибок обязателен.

---

## 5. ESLint (frontend)

```bash
# Авто-фикс (безопасные правки)
task lint:fix

# Финальная проверка
task lint

# Мёртвые экспорты (перед крупным рефактором)
task knip
```

---

## 6. Self-review

Пройди по `git diff main..HEAD` и проверь каждый пункт:

### Код
- [ ] Нет `dd()`, `dump()`, `var_dump()` в PHP
- [ ] Нет `console.log()`, `console.warn()` в JS/TS
- [ ] Нет закомментированных блоков кода
- [ ] Нет TODO/FIXME которые нужно было решить в этом MR

### Backend
- [ ] Новые роуты зарегистрированы в `module/<Module>/routes/api.php`
- [ ] Новые permissions добавлены в enum и `PermissionRegistry`
- [ ] После добавления permissions запущен `php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RoleSeeder`
- [ ] После добавления proxy-эндпоинта запущен `php artisan proxies:sync`
- [ ] Новые env-переменные добавлены в `.env.example`
- [ ] Миграция создана, если изменилась схема БД

### Frontend
- [ ] Нет `any` типов (ESLint ловит, но проверь глазами сложные места)
- [ ] Новые типы/интерфейсы живут в `types/`, не в репозиториях
- [ ] Composable разбит: `useXxxList` / `useXxxModal` / `useXxxFilters` — если страница большая

### API
- [ ] Фильтры передаются как `filter[key]`, не плоскими параметрами
- [ ] Пагинация через `page[number]` / `page[size]`

---

## 7. MR description

Заполни при создании MR:

```markdown
## Что изменено
- Короткое описание что и зачем, не как

## Как проверить
- Шаги для ручной проверки
- Что должно быть видно / какой ответ ожидать

## Миграции / команды
- php artisan migrate
- php artisan proxies:sync  # если добавлен proxy-эндпоинт
- php artisan db:seed --class=PermissionSeeder  # если новые permissions

## Скриншоты
- (для UI-изменений)
```

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Тест упал из-за `Permission not found` | Создать Permission в `setUp()` через `Permission::firstOrCreate(...)` |
| PHPStan падает только на CI, не локально | Проверить версию PHP (`php:8.5-cli`), запустить `task shell` и повторить |
| Rector предлагает изменение вне scope | Не применять автоматически; ограничить пути или исправить вручную |
| Новый gateway — нет env в `.env.example` | Добавить перед коммитом, иначе деплой сломается |
| Забыл запустить `proxies:sync` | Добавить в раздел "Миграции" MR description — DevOps запустит на стейдже |
| Тест для нового эндпоинта не написан | Написать Feature-тест в `tests/Feature/<Module>/Http/` по образцу `UserGroupsControllerTest` |

## References

See [references/pre-mr-checklist.md](references/pre-mr-checklist.md) for the condensed checklist to paste in PR comments.
