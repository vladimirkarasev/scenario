# Рефакторинг кода (Laravel 13 + Vue 3 + Inertia)

Ты — senior fullstack разработчик. Сделай глубокий рефакторинг указанного кода или файла с учётом стека проекта: **Laravel 13, PHP 8.5 (`php:8.5-cli`), Vue 3 (Composition API), TypeScript, Inertia.js, Pinia, TailwindCSS, shadcn-vue**.

## Что рефакторить

$ARGUMENTS

Если аргументы не переданы — рефактори git diff (изменённые файлы в рабочей директории).

---

## Стек-специфичные правила

### PHP / Laravel

**Controller**
- Контроллер — тонкий: только валидация входа, вызов сервиса/экшена, возврат ответа
- Никакой бизнес-логики в контроллере
- Используй Form Request для валидации (`php artisan make:request`)
- Inject зависимости через конструктор, не через `app()` / `resolve()`
- Возвращай `Inertia::render(...)` или `response()->json(...)`, не смешивай

**Service**
- Один сервис — одна ответственность
- Методы должны быть предсказуемы: те же входные данные → тот же результат
- Не обращайся к `request()` внутри сервиса — принимай данные через аргументы
- Тяжёлые операции оборачивай в `DB::transaction()`

**Model**
- Заполняй `$fillable` или `$guarded` явно
- Scopes именуй глаголом: `scopeActive`, `scopeForScenario`
- Relationship-методы — без дополнительной логики
- Кастомные атрибуты через `Attribute::make(get: ..., set: ...)` (Laravel 9+ стиль)
- Не делай запросы внутри геттеров/атрибутов — N+1

**DTO**
- Используй readonly-классы или `spatie/laravel-data`
- DTO — неизменяемый, создаётся через `::from(array|Model|Request)`
- Не храни в DTO Eloquent-модели

**Enum**
- Backed enum (`string` или `int`) для всех статусов и типов
- Добавляй методы `label()`, `color()` — не делай switch в шаблонах/контроллерах

**Общее**
- PHP 8.5: readonly properties/classes, typed properties, match expression, named args, property hooks (`get`/`set`)
- Избегай `array` как возвращаемый тип там, где можно вернуть DTO/Collection
- Удаляй мёртвый код, закомментированные блоки, `dd()`, `dump()`

---

### Vue 3 / TypeScript / Inertia

**Composition API**
- Только `<script setup lang="ts">` — никаких Options API
- Группировка кода: imports → props/emits → state → computed → watchers → методы
- Вычислимые вещи — в `computed()`, не пересчитывай в шаблоне
- Избегай логики в шаблоне — выноси в computed или методы

**TypeScript**
- Все props с явными типами через `defineProps<{...}>()`
- Все emits через `defineEmits<{...}>()`
- Нет `any` — замени на конкретные типы или `unknown`
- Общие типы выноси в `lib/scenario-player-types.ts` или отдельный `types/` файл

**Composables**
- Composable = `use` + существительное, возвращает реактивные ref/computed + методы
- Не держи UI-состояние (модалки, loading) в Pinia — только в локальном composable
- Pinia store — только для глобального/shared состояния между страницами

**Шаблон**
- Используй shadcn-vue компоненты (`Button`, `Input`, `Select` и т.д.) вместо нативных
- Lucide иконки: `import { IconName } from 'lucide-vue-next'`
- Классы — только через TailwindCSS, никаких inline styles
- Длинные условия в шаблоне — в `computed`

**Inertia**
- Передавай из контроллера минимум данных (не весь Eloquent-объект)
- `usePage().props` — для глобальных props (auth, flash)
- `router.visit()` / `router.post()` вместо axios для навигации

---

## Формат ответа

1. **Что нашёл** — список проблем по файлам (кратко, по одной строке на проблему)
2. **Рефакторинг** — показывай изменённый код блоками (старый → новый), объясняй ПОЧЕМУ, а не ЧТО
3. **Что не трогал** — если что-то намеренно оставил — скажи почему
4. После объяснения — примени изменения через Edit/Write

## Важно

- Не добавляй фичи — только улучшай существующее
- Не вводи абстракции если нет минимум 3 повторений
- Не пиши комментарии к очевидному коду
- Сохраняй поведение: рефакторинг не меняет внешний эффект
