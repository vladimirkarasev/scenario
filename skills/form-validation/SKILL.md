---
name: form-validation
description: Build forms using the zod-schema + useZodForm + components/form/ stack. NEVER write manual <Input>/<Textarea>/<Label>/<p class="text-red-600"> markup or hand-rolled try/catch error handling in form composables. Apply when adding a new modal/form, refactoring an existing form, or extending fields in an existing entity form.
---

# Создание формы

Все формы в админке (модалки create/edit и page-level настройки) строятся по единому стеку:

**zod-схема в `modules/<x>/schemas/` → `useZodForm` composable → компоненты из `resources/js/components/form/`**.

Запрещена ручная разметка через сырые `<Input>` + `<Label>` + ручные `<p class="text-red-600">` сообщения об ошибках, ручной `try/catch` с присвоением `formError.value = ...` в save-функции, и валидация типа `!form.name.trim()`.

## Стек

| Слой | Где |
|---|---|
| Схема | `resources/js/modules/<module>/schemas/<entity>Schema.ts` |
| Composable | `resources/js/composables/useZodForm.ts` |
| Поля | `resources/js/components/form/Form*.vue` |
| Тосты успеха | `resources/js/composables/useFormToast.ts` (см. [[crud-toast]]) |
| 422 ошибки | `resources/js/lib/http.ts` → `HttpValidationError` (обрабатывает `useZodForm.submit`) |

## Step 1 — Zod-схема

`resources/js/modules/projects/schemas/projectSchema.ts`:

```ts
import { z } from 'zod'

export const projectSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  sitekey: z.string().min(1, 'Sitekey обязателен'),
  host: z.string().min(1, 'Host обязателен'),
  shared_secret: z.string().min(8, 'Минимум 8 символов'),
  is_active: z.boolean(),
})

export type ProjectFormValues = z.infer<typeof projectSchema>
```

Правила:

- Сообщения об ошибках — на русском, краткие, в формате как пользователь увидит.
- Slug-поля: `.regex(/^[a-z0-9_-]+$/, 'Только латиница, цифры, _ и дефис')`.
- Системные имена (snake_case): `.regex(/^[a-z][a-z0-9_]*$/, 'Только латиница, цифры и _, первый символ — буква')`.
- Email: `.email('Некорректный email')`.
- Поля, зависящие от режима (create vs edit), оформи как функцию: `userSchema(isEditing: boolean) → z.object(...)` — пароль обязательный только при create.

## Step 2 — Composable через useZodForm

```ts
import { ref } from 'vue'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { projectSchema } from '@/modules/projects/schemas/projectSchema'

export function useProjectModal(onSaved: () => void) {
  const showModal = ref(false)
  const editing   = ref<Project | null>(null)

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(projectSchema, {
      name: '', sitekey: '', host: '', shared_secret: '', is_active: true,
    })

  const formToast = useFormToast({
    created: 'Проект создан',
    updated: 'Проект обновлён',
    deleted: 'Проект удалён',
  })

  function openCreate(): void {
    editing.value = null
    reset({ name: '', sitekey: '', host: '', shared_secret: '', is_active: true })
    showModal.value = true
  }

  function openEdit(p: Project): void {
    editing.value = p
    reset({
      name: p.name, sitekey: p.sitekey, host: p.host,
      shared_secret: p.shared_secret ?? '', is_active: p.is_active,
    })
    showModal.value = true
  }

  function close(): void { showModal.value = false; editing.value = null }

  async function save(): Promise<void> {
    const isUpdate = editing.value !== null
    try {
      await submit(async (data) => {
        if (editing.value) {
          await projectRepository.update(editing.value.id, data)
        } else {
          await projectRepository.create(data)
        }
      })
      close()
      onSaved()
      formToast.saved(isUpdate)
    } catch { /* errors уже отображены в форме */ }
  }

  return { showModal, editing, form, errors, formError, submitting,
           openCreate, openEdit, close, save }
}
```

Что даёт `useZodForm`:

- `formData` — reactive объект формы (используй `form.name`, не `form.value.name`).
- `errors` — `Record<string, string>`, ключ = имя поля, значение = первое сообщение.
- `formError` — общая ошибка формы (например, серверная без указания поля).
- `submitting` — флаг во время submit, для `:disabled` на кнопке.
- `submit(onValid)` — сам делает `validate()`, ловит `HttpValidationError` (422) → разносит по полям через `setServerErrors`, ловит остальные ошибки → в `formError`. Бросает наружу для optional finally-логики.
- `reset(values)` — сброс formData + errors + formError.

НЕ нужно: ручной `try/catch` с присвоением `formError.value = e.message`, отдельный `saving` ref, ручной `validate()` перед сабмитом.

## Step 3 — Разметка через Form-компоненты

```vue
<form @submit.prevent="save">
  <FormBody>
    <FormError :message="formError" />
    <FormSection>
      <FormInput
        v-model="form.name"
        label="Название"
        placeholder="Мой проект"
        required
        :error="errors.name"
      />
      <FormInput
        v-model="form.sitekey"
        label="Sitekey"
        placeholder="my-site-key"
        required
        :error="errors.sitekey"
      />
      <FormToggle v-model="form.is_active" label="Активен" />
    </FormSection>
  </FormBody>
  <FormActions
    :submitting="submitting"
    :submit-label="editing ? 'Сохранить' : 'Создать'"
    @cancel="close"
    @submit="save"
  />
</form>
```

### Базовые компоненты

| Компонент | Назначение |
|---|---|
| `<FormBody>` | контейнер с padding + опциональным скроллом |
| `<FormSection>` | секция с опциональным заголовком/описанием |
| `<FormRow>` | grid 2-3 колонки для парных полей |
| `<FormError>` | inline-alert для общей ошибки (`formError`) |
| `<FormActions>` | footer с кнопками Cancel/Submit + slot `#extra` |
| `<FormField>` | low-level: label + error + slot (используется внутри Form*) |
| `<FormInput>` | text/email/url input |
| `<FormPassword>` | password input с eye-toggle |
| `<FormTextarea>` | textarea |
| `<FormSelect>` | NativeSelect + slot для `<option>` |
| `<FormCheckbox>` | один чекбокс с лейблом |
| `<FormToggle>` | switch-toggle (большой, с описанием) |

### Сложные компоненты

| Компонент | Использовать когда |
|---|---|
| `<FormTagSearch>` | debounce-поиск + multi-select chips (роли, группы, теги) |
| `<FormJsonInput>` | JSON-поле с парсингом и подсветкой ошибки |
| `<FormCronInput>` | cron + чипы пресетов |
| `<FormAutoSlug>` | input slug, авто-генерируется из соседнего поля |
| `<FormPermissionGroups>` | группированный чекбокс-список с toggle-all |
| `<FormMockVariants>` | динамический массив webhook mock-вариантов |

**Если поле повторяется в 2+ формах — выноси в `components/form/`**, не оставляй inline. Каждый новый компонент должен оборачивать содержимое в `<FormField :label :error :hint :required :for>` для консистентности.

## HTML-стандарты: id / name / for / a11y

Все Form-компоненты обязаны соблюдать стандарт `<label for>` ↔ `<input id>`:

- **`id`** — у каждого `<input>` / `<textarea>` / `<select>` / `<checkbox>`. Если prop `id` не передан — генерируется через `useFieldId(() => props.id, () => props.name)` (хелпер `resources/js/components/form/useFieldId.ts`). Формат: `{name}-{random6}` если `name` передан, иначе `field-{random6}`. Если передан явный `id` — используется он.
- **`name`** — у каждого `<input>`. По умолчанию `name = id`. Лучше передавать `name` явно (например, `name="email"`) — тогда сгенерированный id будет читаемым (`email-ab12cd`) и autofill / password manager сработают корректно.
- **`<label for>`** — `FormField` принимает prop `for` и передаёт его в `<Label>`. Компонент-обёртка считает `fieldId` через `useFieldId(() => props.id)` и пробрасывает в `<FormField :for="fieldId">` и в сам input `:id="fieldId"`.
- **`autocomplete`** — обязателен на каждом текстовом контроле. Все Form-компоненты с инпутом по умолчанию ставят `autocomplete="off"`; служебные поля (JSON, cron, search в FormTagSearch) — захардкожено `"off"`. **Если поле имеет смысловое значение — передавай корректный токен** из [WHATWG autofill list](https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofill): `name`, `given-name`, `family-name`, `email`, `username`, `new-password`, `current-password`, `organization`, `tel`, `url`, `street-address`, `postal-code`. Это убирает Chrome accessibility warning «An element doesn't have an autocomplete attribute».
- **`required`** — пробрасывается на нативный input в дополнение к визуальной звёздочке в label (для form-validation API браузера).
- **`aria-invalid`** — `true` когда есть `error`, для скрин-ридеров.
- **`role` / `aria-*`** — для нестандартных контролов: `FormToggle` имеет `role="switch"`/`aria-checked`/`aria-label`; `FormTagSearch` — `role="combobox"`/`aria-expanded`/`aria-autocomplete`.
- **Кнопки-иконки** (eye-toggle, remove-tag) — обязан быть `aria-label` с осмысленным текстом.
- **`spellcheck="false"`** — для технических полей (JSON, cron, slug, ключи, коды), уже включён в FormJsonInput.

**Карта autocomplete-токенов для типичных полей:**

| Поле | autocomplete |
|---|---|
| Имя пользователя | `name` |
| Email | `email` |
| Логин (username) | `username` |
| Пароль (новый) | `new-password` |
| Пароль (вход) | `current-password` |
| Название компании / проекта | `organization` |
| Телефон | `tel` |
| URL / host | `url` |
| Технические идентификаторы (slug, key, secret, JSON, cron) | `off` |

При добавлении нового Form-компонента — всегда брать `useFieldId`, добавлять `id?`/`name?` в props, привязывать `for` к label через `FormField`.

Минимальный правильный шаблон:

```vue
<script setup lang="ts">
import { Input } from '@/components/ui/input'
import FormField from './FormField.vue'
import { useFieldId } from './useFieldId'

const props = defineProps<{
  modelValue: string
  id?: string
  name?: string
  label?: string
  error?: string
  required?: boolean
}>()
defineEmits<{ 'update:modelValue': [value: string] }>()
const fieldId = useFieldId(() => props.id)
</script>

<template>
  <FormField :label="label" :error="error" :required="required" :for="fieldId">
    <Input
      :id="fieldId"
      :name="name || fieldId"
      :model-value="modelValue"
      :required="required"
      :aria-invalid="!!error || undefined"
      @update:model-value="$emit('update:modelValue', String($event ?? ''))"
    />
  </FormField>
</template>
```

**Запрещено:**
- Создавать Form-компонент без `id`/`name` пропсов и без `useFieldId`.
- Писать `<Label>` без `:for` — связь label ↔ input должна быть всегда.
- Использовать сырой `<input>` без `id`/`name`/`aria-invalid` (если не оборачиваешь в FormField — всё равно эти атрибуты ставь).

## Step 4 — Модалка не должна вылезать за экран

Контейнер: `flex flex-col max-h-[90vh]`. Header и `FormActions` — `shrink-0`. Form: `flex min-h-0 flex-1 flex-col`. `FormBody` (по умолчанию `scrollable=true`) сам станет `flex-1 overflow-y-auto`.

```vue
<div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border bg-white">
  <div class="shrink-0 ...">…header…</div>
  <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="save">
    <FormBody>…fields…</FormBody>
    <FormActions class="shrink-0" :submitting @cancel @submit />
  </form>
</div>
```

## Step 5 — Тосты после успеха

См. [[crud-toast]]. После `submit()` без исключения: `formToast.saved(isUpdate)`. После delete: `formToast.deleted()`. На ошибки внутри submit полагаемся на errors/formError, плюс при желании `formToast.error(e, ...)`.

## Запрещено

- **Ручная разметка полей**: `<Label>` + `<Input v-model>` + ручной `<p v-if="errors.x" class="text-red-600">`. Это всё инкапсулировано в `<FormInput :error>`.
- **Ручной try/catch вокруг save**: если ловишь и присваиваешь `formError.value = ...` — переезжай на `useZodForm.submit`.
- **Валидация типа `!form.name.trim()`** в шаблоне или composable: пиши zod-схему.
- **`alert()` для ошибок**: см. [[crud-toast]].
- **Дублирование схемы**: типы формы — `z.infer<typeof xxxSchema>`, не объявляй отдельный `interface XxxForm`.

## Существующие формы — образцы

| Сложность | Файл |
|---|---|
| Минимум | `useProjectModal.ts` + `Pages/Projects/Index.vue` |
| Auto-slug | `useGroupModal.ts` + `Pages/Users/Groups.vue` |
| Tag-search | `useUserModal.ts` + `Pages/Users/Index.vue` |
| Permission groups | `useRoleModal.ts` + `Pages/Users/Roles.vue` |
| JSON + cron | `useActionScheduleModal.ts` |
| Динамические config-поля + nested input_fields | `useActionModal.ts` + `Pages/Actions/Index.vue` |
| Page-level (без модалки) | `Pages/Scenario/ScenarioEditor.vue` |

## Связанные

- [[crud-toast]] — тосты при CRUD-операциях.
- См. `skills/add-new-page/SKILL.md` — общий паттерн страницы (List + Modal + Filters); при создании Modal-композабла применять этот skill.
