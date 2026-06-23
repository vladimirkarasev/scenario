---
name: crud-toast
description: Use toast notifications via useFormToast on every CRUD operation (create/update/delete/toggle). NEVER use inline success banners with setTimeout or alert()/window.alert. Apply when adding or modifying any composable that calls repository.create/update/remove or when reviewing CRUD success/error UX in admin forms.
---

# CRUD → useFormToast

При каждой CRUD-операции (create / update / delete / toggle is_active и т.п.) показывать тосты через `useFormToast`. Запрещены: inline-баннеры с `successMsg` + `setTimeout`, `alert()`, `window.alert`.

Файл: `resources/js/composables/useFormToast.ts`.

## API

```ts
const formToast = useFormToast({
  created: 'Проект создан',
  updated: 'Проект обновлён',
  deleted: 'Проект удалён',  // optional
})

formToast.saved(isUpdate)              // success: created или updated
formToast.deleted()                    // success: deleted
formToast.error(e, 'Ошибка сохранения.')  // error: e.message или fallback
```

## Текст тостов

- **Всегда с указанием сущности** + правильный род: «Проект сохранён» (м.р.), «Роль создана» (ж.р.), «Группа удалена», «Расписание сохранено».
- НЕ безличные «Сохранено», «Удалено» — пользователь должен понимать, что именно произошло.
- НЕ заглавные технические термины («Action создан» допустим, потому что это название сущности в UI).

## Применение

### 1. В composable модалки

```ts
import { useFormToast } from '@/composables/useFormToast'

export function useProjectModal(onSaved: () => void) {
  const formToast = useFormToast({
    created: 'Проект создан',
    updated: 'Проект обновлён',
    deleted: 'Проект удалён',
  })

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
    } catch { /* errors уже в форме через useZodForm */ }
  }

  async function doDelete(): Promise<void> {
    try {
      await projectRepository.remove(confirmDelete.value.id)
      closeDeleteConfirm()
      onSaved()
      formToast.deleted()
    } catch (e) {
      deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
      formToast.error(e, 'Ошибка удаления.')
    }
  }

  async function toggleActive(p: Project): Promise<void> {
    try {
      await projectRepository.update(p.id, { ...p, is_active: !p.is_active })
      p.is_active = !p.is_active
      formToast.saved(true)
    } catch (e) {
      formToast.error(e, 'Ошибка обновления.')
    }
  }
}
```

### 2. На странице (page-level форма, без модалки)

Если форма прямо на странице (например, `ScenarioEditor.vue`), а композабл-обёртки нет — используй `toast` напрямую из vue-sonner:

```ts
import { toast } from 'vue-sonner'

async function save() {
  try {
    await submit(async (data) => { await repo.update(id, data) })
    toast.success('Сценарий обновлён')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : 'Ошибка сохранения')
  }
}
```

## Разделение success vs error

| Источник | Куда |
|---|---|
| Валидация zod / 422 от сервера на поле | `errors[key]` → отображается под полем через `<FormInput :error>` |
| Общая серверная ошибка (без поля) | `formError` ref → `<FormError :message="formError" />` наверху формы |
| Финальный успех | `formToast.saved()` / `formToast.deleted()` / `toast.success(...)` |
| Сетевая ошибка / unexpected | `formToast.error(e, fallback)` + `formError` если нужно показать в форме |

**Тосты — только для финального результата CRUD-операции.** Они не дублируют per-field errors.

## Запрещено

- **Inline success-баннеры в страницах**: `const successMsg = ref(''); successMsg.value = '...'; setTimeout(...)`. Всё это уже было вычищено.
- **`alert(...)` / `window.alert`** для уведомления о результате. Только `window.confirm` допустим — и только для destructive actions, и лучше через `confirmDelete` dialog.
- **Дублирование тоста и formError для одной ошибки**: либо одно, либо другое, в зависимости от типа.
- **Тост на каждое поле**: per-field — это `errors[key]`, тост — только финальный итог.

## Связанные

- [[form-validation]] — zod-схемы + useZodForm + components/form (тосты идут после успешного submit оттуда).
- `resources/js/components/form/FormError.vue` — компонент для общей ошибки формы (то, что НЕ тост).
- `resources/js/lib/http.ts` — `HttpValidationError` для 422; `useZodForm.submit` сам обрабатывает.

## Existing examples in code

- `useProjectModal`, `useUserModal`, `useGroupModal`, `useRoleModal` — модалки.
- `useActionRunModal`, `useActionScheduleModal` — отдельные действия (запуск/расписание).
- `ScenarioEditor.vue`, `ScenarioVersionEditor.vue` — page-level через `toast` напрямую.
