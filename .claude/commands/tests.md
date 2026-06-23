# Написать тесты (Laravel 13 + PHPUnit 12)

Ты — senior backend разработчик. Напиши тесты для указанного кода с учётом стека проекта.

## Что тестировать

$ARGUMENTS

Если аргументы не переданы — посмотри на git diff и напиши тесты для изменённого кода.

---

## Контекст проекта

**Стек:** Laravel 13, PHP 8.5 (`php:8.5-cli`), PHPUnit 12, SQLite in-memory, Mockery  
**Тест-база:** `RefreshDatabase` + SQLite `:memory:` (настроено в `tests/TestCase.php`)  
**Паттерн:** тесты вызывают **сервисы напрямую** через `app(ServiceClass::class)`, а не через HTTP — это основной стиль проекта (см. `ScenarioPlayerTest`, `ScenarioCatalogTest`)  
**Модули:** `module/Scenario/`, `module/Actions/`, `module/Directories/`, `module/Projects/`

---

## Правила написания тестов

### Структура

- **Unit** (`tests/Unit/`) — изолированная логика без БД: Enum-методы, DTO-фабрики, хелперы, `ConditionEvaluator`, `VariableResolver`
- **Feature** (`tests/Feature/`) — интеграционные тесты с БД: сервисы, контроллеры через HTTP, полный сценарий

### Именование

- Метод: `test_<что>_<при каком условии>_<ожидаемый результат>()`
- Примеры:
  - `test_create_run_auto_progresses_past_start_node()`
  - `test_condition_uses_fallback_when_no_rule_matches()`
  - `test_update_scenario_persists_description_to_database()`
- Никаких `testSomethingWorks` — имя должно быть читаемым как спецификация

### Подготовка данных

- Создавай модели через `Model::query()->create([...])` — **не используй Factory** если их нет в проекте
- Строй минимальный граф: только узлы, нужные для теста
- Используй приватные builder-методы (как `startNode()`, `blockNode()`, `edge()` в `ScenarioPlayerTest`) — не дублируй массивы в каждом тесте
- Для `ScenarioVersion` заполняй `schema_json`, `nodes_json`, `edges_json`, `schema_version`

### Вызов кода

- Сервисы: `app(ScenarioPlayerService::class)->method(new DTO(...))`
- HTTP: `$this->actingAs($user)->postJson('/api/...', [...])->assertOk()`
- Для Inertia-страниц: `$this->actingAs($user)->get('/dashboard/...')->assertInertia(fn($page) => $page->component('Dashboard/Page'))`
- Mockery: только когда нужно изолировать внешний сервис (HTTP-запросы, очереди), не мокай свои сервисы

### Ассерты

- Предпочитай `assertSame` над `assertEquals` (строгое сравнение)
- `data_get($payload, 'run.current_node_id')` — для проверки вложенных массивов
- `assertDatabaseHas('table', ['col' => 'val'])` — для проверки персистентности
- `assertDatabaseMissing` — для проверки удаления
- `$this->expectException(ValidationException::class)` — до вызова кода

### Покрытие

Для каждого метода/фичи пиши минимум:
1. **Happy path** — успешный сценарий
2. **Edge case** — граничное условие (пустой массив, null, 0)
3. **Error path** — невалидные данные, несуществующий ID, нарушение бизнес-правил

### Что НЕ делать

- Не тестируй реализацию — тестируй поведение (что возвращается/сохраняется)
- Не делай тест зависящим от другого теста
- Не используй `sleep()`, моки времени — используй `Carbon::setTestNow()`
- Не проверяй точные SQL-запросы
- Не тестируй чужой код (Laravel, пакеты)

---

## Формат ответа

1. **Что буду тестировать** — список кейсов, по одной строке
2. **Куда кладу файл** — `tests/Unit/...` или `tests/Feature/...` и почему
3. **Код теста** — полный файл с namespace, use, классом
4. После объяснения — создай файл через Write

Запусти тесты командой:
```bash
php artisan test --filter=НазваниеКласса
```
и убедись что они проходят (или объясни почему не могут без доп. настройки).
