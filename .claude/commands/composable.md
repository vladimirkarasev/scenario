# Создание Vue 3 composable

Ты — senior frontend разработчик. Создай composable по паттерну, принятому в этом проекте.

## Что создать

$ARGUMENTS

Если аргументы не переданы — проанализируй текущую страницу (или ближайший Vue-файл в git diff) и предложи, какие composables стоит вынести.

---

## Паттерн composables в этом проекте

Каждая страница с CRUD-логикой разбивается на три типа composables. Живут в `resources/js/composables/`.

### 1. `useXxxList` — список + пагинация + поиск

```ts
import { onMounted, ref, watch } from 'vue'
import { xxxRepository } from '@/repositories/xxxRepository'
import type { Xxx, XxxsPage } from '@/types/xxx'

export function useXxxList() {
  const search  = ref('')
  const page    = ref(1)
  const loading = ref(false)
  const items   = ref<Xxx[]>([])
  const meta    = ref<XxxsPage['meta']>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

  let searchTimer: ReturnType<typeof setTimeout> | null = null

  async function load(): Promise<void> {
    loading.value = true
    try {
      const result = await xxxRepository.list({ page: page.value, search: search.value || undefined })
      items.value = result.data
      meta.value  = result.meta
    } catch { /* silent */ }
    finally { loading.value = false }
  }

  function resetAndLoad(): void { page.value = 1; load() }

  onMounted(load)
  watch(page, load)
  watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(resetAndLoad, 300)
  })

  return { search, page, loading, items, meta, load, resetAndLoad }
}
```

**Когда добавлять фильтры** — если нужна фильтрация по ref-массивам (группы, роли и т.п.), принимай их как аргументы и добавляй `watch([filterA, filterB], resetAndLoad, { deep: true })`. Пример: `useUserList(filterGroups, filterRoles)`.

---

### 2. `useXxxModal` — создание, редактирование, удаление

```ts
import { ref } from 'vue'
import { xxxRepository } from '@/repositories/xxxRepository'
import type { Xxx } from '@/types/xxx'

const emptyForm = () => ({ field1: '', field2: '' })

export function useXxxModal(onSaved: () => void) {
  const showModal    = ref(false)
  const editing      = ref<Xxx | null>(null)
  const form         = ref(emptyForm())
  const formError    = ref<string | null>(null)
  const modalLoading = ref(false)

  function openCreate(): void {
    editing.value = null
    form.value = emptyForm()
    formError.value = null
    showModal.value = true
  }

  async function openEdit(item: Xxx): Promise<void> {
    editing.value = item
    form.value = emptyForm()
    formError.value = null
    showModal.value = true
    modalLoading.value = true
    try {
      const fresh = await xxxRepository.find(item.id)
      form.value = { field1: fresh.field1, field2: fresh.field2 }
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Ошибка загрузки.'
    } finally { modalLoading.value = false }
  }

  function close(): void { showModal.value = false; editing.value = null }

  async function save(): Promise<void> {
    formError.value = null
    try {
      if (editing.value) {
        await xxxRepository.update(editing.value.id, form.value)
      } else {
        await xxxRepository.create(form.value)
      }
      close()
      onSaved()
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Ошибка сохранения.'
    }
  }

  // ── Delete confirm ───────────────────────────────────────────────────

  const confirmDelete = ref<Xxx | null>(null)
  const deleting      = ref(false)
  const deleteError   = ref<string | null>(null)

  function openDeleteConfirm(item: Xxx): void { confirmDelete.value = item; deleteError.value = null }
  function closeDeleteConfirm(): void { confirmDelete.value = null; deleteError.value = null }

  async function doDelete(): Promise<void> {
    if (!confirmDelete.value) return
    deleting.value = true
    deleteError.value = null
    try {
      await xxxRepository.remove(confirmDelete.value.id)
      closeDeleteConfirm()
      onSaved()
    } catch (e: unknown) {
      deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
    } finally { deleting.value = false }
  }

  return {
    showModal, editing, form, formError, modalLoading,
    openCreate, openEdit, close, save,
    confirmDelete, deleting, deleteError,
    openDeleteConfirm, closeDeleteConfirm, doDelete,
  }
}
```

---

### 3. `useXxxFilters` — фильтры с мульти-выбором (опционально)

Нужен когда на странице есть dropdown-фильтры с поиском и чипами. Пример: `useUserFilters`.

```ts
import { computed, ref } from 'vue'
import { someRepository } from '@/repositories/someRepository'

export type FilterItem = { id: string; name: string }

export function useXxxFilters() {
  const filterItems  = ref<FilterItem[]>([])
  const itemSearch   = ref('')
  const itemOpen     = ref(false)
  const itemResults  = ref<FilterItem[]>([])
  let timer: ReturnType<typeof setTimeout> | null = null

  const hasFilters = computed(() => filterItems.value.length > 0)

  function onItemInput(): void {
    if (timer) clearTimeout(timer)
    if (!itemSearch.value.trim()) { itemResults.value = []; return }
    timer = setTimeout(async () => {
      try {
        const res = await someRepository.list({ search: itemSearch.value })
        itemResults.value = res.data
          .filter(i => !filterItems.value.some(f => f.id === i.id))
          .map(i => ({ id: i.id, name: i.name }))
      } catch { /* silent */ }
    }, 250)
  }

  function addItem(i: FilterItem): void {
    filterItems.value.push(i)
    itemSearch.value = ''
    itemResults.value = []
    itemOpen.value = false
  }

  function removeItem(id: string): void {
    filterItems.value = filterItems.value.filter(i => i.id !== id)
  }

  function clear(): void { filterItems.value = [] }

  return { filterItems, hasFilters, itemSearch, itemOpen, itemResults, onItemInput, addItem, removeItem, clear }
}
```

---

## Как подключить в странице

```ts
// script setup страницы — только склейка composables
const filters = useXxxFilters()
const list    = useXxxList(filters.filterItems)
const modal   = useXxxModal(list.load)

// Деструктурирование с алиасами для избежания конфликтов имён
const { filterItems, hasFilters, onItemInput: onFilterInput, addItem: addFilterItem, ... } = filters
const { search, page, loading, items, meta, load } = list
const { showModal, editing, form, openCreate, openEdit, close: closeModal, save, ... } = modal
```

**Правило алиасов**: если два composable экспортируют функцию с одинаковым именем (например, `onGroupInput` в фильтрах и в модальном окне) — разрешай через алиас при деструктуризации.

---

## Готовые composables (примеры в проекте)

| Файл | Тип | Что делает |
|------|-----|-----------|
| `useUserList.ts` | List + фильтры | Список пользователей, принимает `filterGroups`, `filterRoles` |
| `useUserModal.ts` | Modal | CRUD пользователей + поиск ролей/групп в форме |
| `useUserFilters.ts` | Filters | Мульти-фильтр по группам и ролям |
| `useGroupList.ts` | List | Список групп с пагинацией |
| `useGroupModal.ts` | Modal | CRUD групп + управление участниками |
| `useRoleList.ts` | List | Список ролей + загрузка разрешений |
| `useRoleModal.ts` | Modal | CRUD ролей + управление разрешениями |

---

## Правила

- Нет `any` — все типы явные
- `catch { /* silent */ }` для фоновых загрузок, `formError` / `deleteError` для пользовательских действий
- `onSaved` — единственный способ уведомить страницу о сохранении/удалении; страница решает что делать (обычно `load()`)
- Не держи производные данные в `ref` — используй `computed`
- Не импортируй `userRepository` / `groupRepository` в два разных composable одного модуля без причины
