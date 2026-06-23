---
name: add-new-page
description: Add a new Inertia.js page to the frontend — page component, composables (useXxxList/Modal/Filters), repository, types, and backend controller with Inertia::render(). Use when asked to add a new section, screen, or admin page.
---

# Add New Inertia Page

## Directory layout

```
resources/js/
  Pages/<Module>/
    Index.vue              ← страница (только разметка + вызов composables)
  modules/<module>/
    composables/
      use<Resource>List.ts    ← загрузка + пагинация
      use<Resource>Modal.ts   ← create/edit/delete форма
      use<Resource>Filters.ts ← фильтры и dropdown-данные (если нужно)
    repositories/
      <resource>Repository.ts ← все HTTP-вызовы
    types/
      <resource>.ts           ← интерфейсы и типы

module/<Module>/
  Http/Controllers/<Resource>PageController.php  ← возвращает Inertia::render()
  routes/web.php                                  ← web-роут
```

---

## Step 1 — Типы

```ts
// resources/js/modules/widgets/types/widget.ts
import type { PaginationMeta } from '@/types/pagination'

export interface Widget {
  id: string
  name: string
  slug: string
  description: string | null
  is_active: boolean
  created_at: string | null
}

export interface WidgetsPage {
  data: Widget[]
  meta: PaginationMeta
}

export interface WidgetPayload {
  name: string
  slug: string
  description: string
  is_active: boolean
}
```

---

## Step 2 — Репозиторий

```ts
// resources/js/modules/widgets/repositories/widgetRepository.ts
import { destroyJson, getJson, sendJson } from '@/lib/http'
import type { Widget, WidgetPayload, WidgetsPage } from '@/modules/widgets/types/widget'

interface RawWidget {
  id: string
  attributes: {
    name: string
    slug: string
    description: string | null
    is_active: boolean
    created_at: string | null
  }
}

function normalize(item: RawWidget): Widget {
  return {
    id: item.id,
    name: item.attributes.name,
    slug: item.attributes.slug,
    description: item.attributes.description,
    is_active: item.attributes.is_active,
    created_at: item.attributes.created_at,
  }
}

export const widgetRepository = {
  async list(qs: URLSearchParams): Promise<WidgetsPage> {
    const raw = await getJson(`/api/widgets?${qs}`, 'Не удалось загрузить виджеты.') as {
      data: RawWidget[]
      meta: WidgetsPage['meta']
    }
    return { data: raw.data.map(normalize), meta: raw.meta }
  },

  async find(id: string): Promise<Widget> {
    const raw = await getJson(`/api/widgets/${id}`, 'Не удалось загрузить виджет.') as { data: RawWidget }
    return normalize(raw.data)
  },

  async create(payload: WidgetPayload): Promise<void> {
    await sendJson('/api/widgets', { method: 'POST', body: payload, fallbackMessage: 'Не удалось создать виджет.' })
  },

  async update(id: string, payload: WidgetPayload): Promise<void> {
    await sendJson(`/api/widgets/${id}`, { method: 'PUT', body: payload, fallbackMessage: 'Не удалось сохранить виджет.' })
  },

  async remove(id: string): Promise<void> {
    await destroyJson(`/api/widgets/${id}`, 'Не удалось удалить виджет.')
  },
}
```

HTTP-функции `getJson`, `sendJson`, `destroyJson` из `@/lib/http` — не использовать `axios` напрямую.

---

## Step 3 — Composables

### useWidgetList

```ts
// resources/js/modules/widgets/composables/useWidgetList.ts
import { onMounted, ref, watch } from 'vue'
import { widgetRepository } from '@/modules/widgets/repositories/widgetRepository'
import type { Widget, WidgetsPage } from '@/modules/widgets/types/widget'

export function useWidgetList() {
  const search  = ref('')
  const page    = ref(1)
  const loading = ref(false)
  const items   = ref<Widget[]>([])
  const meta    = ref<WidgetsPage['meta']>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

  let searchTimer: ReturnType<typeof setTimeout> | null = null

  async function load(): Promise<void> {
    loading.value = true
    try {
      const qs = new URLSearchParams({ 'page[number]': String(page.value), 'page[size]': '15' })
      if (search.value) qs.set('filter[search]', search.value)
      const result = await widgetRepository.list(qs)
      items.value = result.data
      meta.value  = result.meta
    } catch { /* silent */ }
    finally { loading.value = false }
  }

  onMounted(load)
  watch(page, load)
  watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { page.value = 1; load() }, 300)
  })

  return { search, page, loading, items, meta, load }
}
```

### useWidgetModal

**Применяй skill `form-validation`** для CRUD-формы: zod-схема + `useZodForm` + компоненты из `@/components/form/`. **Применяй skill `crud-toast`** для тостов на save/delete.

Краткий каркас (полный паттерн — в `skills/form-validation/SKILL.md`):

```ts
// resources/js/modules/widgets/schemas/widgetSchema.ts
import { z } from 'zod'

export const widgetSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  slug: z.string().min(1, 'Slug обязателен').regex(/^[a-z0-9-]+$/, 'Только латиница, цифры и дефис'),
  description: z.string(),
  is_active: z.boolean(),
})
export type WidgetFormValues = z.infer<typeof widgetSchema>
```

```ts
// resources/js/modules/widgets/composables/useWidgetModal.ts
import { ref } from 'vue'
import { widgetRepository } from '@/modules/widgets/repositories/widgetRepository'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { widgetSchema } from '@/modules/widgets/schemas/widgetSchema'
import type { Widget } from '@/modules/widgets/types/widget'

export function useWidgetModal(onSaved: () => void) {
  const showModal = ref(false)
  const editing   = ref<Widget | null>(null)

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(widgetSchema, { name: '', slug: '', description: '', is_active: true })

  const formToast = useFormToast({
    created: 'Виджет создан',
    updated: 'Виджет обновлён',
    deleted: 'Виджет удалён',
  })

  function openCreate(): void {
    editing.value = null
    reset({ name: '', slug: '', description: '', is_active: true })
    showModal.value = true
  }

  function openEdit(item: Widget): void {
    editing.value = item
    reset({ name: item.name, slug: item.slug, description: item.description ?? '', is_active: item.is_active })
    showModal.value = true
  }

  function close(): void { showModal.value = false; editing.value = null }

  async function save(): Promise<void> {
    const isUpdate = editing.value !== null
    try {
      await submit(async (data) => {
        if (editing.value) {
          await widgetRepository.update(editing.value.id, data)
        } else {
          await widgetRepository.create(data)
        }
      })
      close()
      onSaved()
      formToast.saved(isUpdate)
    } catch { /* errors уже в форме */ }
  }

  // Delete confirm
  const confirmDelete = ref<Widget | null>(null)
  const deleting      = ref(false)
  const deleteError   = ref<string | null>(null)

  function openDeleteConfirm(item: Widget): void { confirmDelete.value = item; deleteError.value = null }
  function closeDeleteConfirm(): void { confirmDelete.value = null; deleteError.value = null }

  async function doDelete(): Promise<void> {
    if (!confirmDelete.value) return
    deleting.value = true
    try {
      await widgetRepository.remove(confirmDelete.value.id)
      closeDeleteConfirm()
      onSaved()
      formToast.deleted()
    } catch (e: unknown) {
      deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
      formToast.error(e, 'Ошибка удаления.')
    } finally {
      deleting.value = false
    }
  }

  return {
    showModal, editing, form, errors, formError, submitting,
    openCreate, openEdit, close, save,
    confirmDelete, deleting, deleteError, openDeleteConfirm, closeDeleteConfirm, doDelete,
  }
}
```

Запрещено: ручная разметка `<Label>` + `<Input>` + `<p class="text-red-600">`, `alert()` для ошибок, ручной try/catch с `formError.value = ...` вокруг save. См. `skills/form-validation/SKILL.md`.

---

## Step 4 — Page component

```vue
<!-- resources/js/Pages/Widgets/Index.vue -->
<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import ListPagination from '@/components/ListPagination.vue'
import SearchInput from '@/components/SearchInput.vue'
import EmptyState from '@/components/EmptyState.vue'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { useWidgetList } from '@/modules/widgets/composables/useWidgetList'
import { useWidgetModal } from '@/modules/widgets/composables/useWidgetModal'
import { Head } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'

const { navigationItems } = useDashboardNavigation()
const { search, page, loading, items, meta, load } = useWidgetList()
const { showModal, editing, form, openCreate, openEdit, save, openDeleteConfirm, closeDeleteConfirm, confirmDelete, deleting, deleteError, doDelete } = useWidgetModal(load)
</script>

<template>
  <Head title="Виджеты" />

  <AppShell title="Виджеты" :navigation-items="navigationItems">
    <div class="mx-auto max-w-6xl px-6 py-8">
      <PageHeader title="Виджеты" subtitle="Управление виджетами.">
        <template #actions>
          <button
            class="inline-flex h-9 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white hover:bg-blue-700"
            @click="openCreate"
          >
            <Plus :size="15" /> Новый виджет
          </button>
        </template>
      </PageHeader>

      <div class="mb-4">
        <SearchInput v-model="search" placeholder="Поиск..." />
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div v-if="loading" class="flex items-center justify-center py-12 text-slate-400">Загрузка…</div>
        <EmptyState v-else-if="!items.length" title="Нет виджетов" subtitle="Создайте первый виджет" />
        <div v-for="item in items" :key="item.id" class="border-b border-slate-100 px-5 py-3.5">
          {{ item.name }}
        </div>
        <ListPagination v-model:current-page="page" :total-pages="meta.last_page" :total="meta.total" :per-page="meta.per_page" />
      </div>
    </div>
  </AppShell>
</template>
```

Страница — только разметка. Вся логика в composables.

---

## Step 5 — Backend controller (web route)

```php
// module/Widgets/Http/Controllers/WidgetPageController.php
final class WidgetPageController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Widgets/Index');
    }
}
```

Данные для страницы (если нужны на SSR) передавай вторым аргументом:

```php
return Inertia::render('Widgets/Index', [
    'canCreate' => $request->user()?->hasPermissionTo('widget_create'),
]);
```

---

## Step 6 — Web route

```php
// module/Widgets/routes/web.php
Route::middleware(['web', 'auth'])->group(static function (): void {
    Route::get('/widgets', [WidgetPageController::class, 'index'])->name('widgets.index');
});
```

Зарегистрируй в ServiceProvider:

```php
Route::middleware('web')
    ->group(dirname(__DIR__).'/routes/web.php');
```

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Нужны фильтры (dropdown, checkbox) | Создай `useWidgetFilters.ts` — хранит состояние фильтров + загружает справочные данные |
| Страница с вкладками | Отдельный composable на каждую вкладку, переключение через `activeTab` ref |
| Данные нужны немедленно (SSR) | Передай через `Inertia::render('...', [...])`, прими через `defineProps` |
| Нужна навигация между страницами | `router.visit('/widgets')` из `@inertiajs/vue3` |
| Пагинация на URL (для share-able links) | Читай `page[number]` из `window.location.search` в `onMounted` |
| Модальное окно с tabs (create/edit) | Разбей на два composable или передавай `mode: 'create' | 'edit'` |

## References

See [references/add-new-page-checklist.md](references/add-new-page-checklist.md) for the quick checklist.
