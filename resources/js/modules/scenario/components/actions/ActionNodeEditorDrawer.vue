<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'
import { Plus, Zap, AlertCircle, PlayCircle, Check, Copy } from 'lucide-vue-next'
import AppEditorDrawer from '@/components/AppEditorDrawer.vue'
import { FormBody, FormField, FormRow, FormToggle } from '@/components/form'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { actionRepository } from '@/modules/actions/repositories/actionRepository'
import type { Action, ActionInputField } from '@/modules/actions/types/action'
import ActionPickerDialog from '../pickers/ActionPickerDialog.vue'
import ActionItemCard from './ActionItemCard.vue'
import { USER_VARIABLES } from '@/modules/scenario/lib/scenario-flow-constants'

const props = withDefaults(defineProps<{
    open: boolean
    nodeId?: string
    nodeData: ActionNodeData
    editable?: boolean
    variables?: VariableItem[]
}>(), {
    nodeId: '',
    editable: true,
    variables: () => [],
})

const emit = defineEmits<{
    'update:open': [value: boolean]
    update: [data: Partial<ActionNodeData>]
}>()

interface VariableItem {
    id: string
    blockId: string
    blockTitle: string
    varRef: string
    label: string
}

interface ActionItem {
    id: string
    code: string
    action_id: string
    name: string
    input: Record<string, unknown>
    retries: number
}

interface ActionNodeData {
    title: string
    variable: string
    skipInSurvey: boolean
    wait_for_result: boolean
    execution_mode: 'sequential' | 'parallel'
    delay_between: number | null
    retries: number
    action_items: ActionItem[]
    before_items: ActionItem[]
    error_items: ActionItem[]
    // legacy (single hook) — заполняется из старых нод, очищается при save
    before_action_id?: string
    before_code?: string
    before_input?: Record<string, unknown>
    error_action_id?: string
    error_code?: string
    error_input?: Record<string, unknown>
    [key: string]: unknown
}

// ── Draft ──────────────────────────────────────────────────────────────────────

const draft = reactive<ActionNodeData>({
    title: '',
    variable: '',
    skipInSurvey: false,
    wait_for_result: false,
    execution_mode: 'sequential',
    delay_between: 180,
    retries: 3,
    action_items: [],
    before_items: [],
    error_items: [],
})

function asString(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback
}

function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === 'object' && !Array.isArray(value) ? { ...(value as Record<string, unknown>) } : {}
}

function hydrateItems(raw: unknown): ActionItem[] {
    if (!Array.isArray(raw)) return []
    return raw.map((item) => ({
        id: asString((item as ActionItem | undefined)?.id) || uid(),
        code: asString((item as ActionItem | undefined)?.code),
        action_id: asString((item as ActionItem | undefined)?.action_id),
        name: asString((item as ActionItem | undefined)?.name),
        input: asRecord((item as ActionItem | undefined)?.input),
        retries: typeof (item as ActionItem | undefined)?.retries === 'number' ? (item as ActionItem).retries : 1,
    }))
}

function hydrateFromProps(): void {
    draft.title = asString(props.nodeData.title)
    draft.variable = asString(props.nodeData.variable)
    draft.skipInSurvey = Boolean(props.nodeData.skipInSurvey)
    draft.wait_for_result = Boolean(props.nodeData.wait_for_result)
    draft.execution_mode = props.nodeData.execution_mode === 'parallel' ? 'parallel' : 'sequential'
    draft.delay_between = props.nodeData.delay_between != null ? Number(props.nodeData.delay_between) : 180
    draft.retries = props.nodeData.retries !== undefined ? Number(props.nodeData.retries) : 3
    draft.action_items = hydrateItems(props.nodeData.action_items)
    draft.before_items = hydrateItems(props.nodeData.before_items)
    draft.error_items = hydrateItems(props.nodeData.error_items)

    // Миграция legacy single hook → первый элемент массива.
    if (draft.before_items.length === 0 && props.nodeData.before_action_id) {
        draft.before_items.push({
            id: uid(),
            code: asString(props.nodeData.before_code),
            action_id: asString(props.nodeData.before_action_id),
            name: '',
            input: asRecord(props.nodeData.before_input),
            retries: 1,
        })
    }
    if (draft.error_items.length === 0 && props.nodeData.error_action_id) {
        draft.error_items.push({
            id: uid(),
            code: asString(props.nodeData.error_code),
            action_id: asString(props.nodeData.error_action_id),
            name: '',
            input: asRecord(props.nodeData.error_input),
            retries: 1,
        })
    }
}

watch(() => props.open, async (val) => {
    if (val) {
        hydrateFromProps()
        await loadActions()
        const allItems = [...draft.action_items, ...draft.before_items, ...draft.error_items]
        await Promise.all(allItems.map(i => i.action_id ? loadFields(i.action_id) : Promise.resolve()))
    }
})

function save(): void {
    const cloneItems = (items: ActionItem[]) => items.map(item => ({ ...item, input: { ...item.input } }))
    emit('update', {
        title: draft.title,
        variable: draft.variable,
        skipInSurvey: draft.skipInSurvey,
        wait_for_result: draft.wait_for_result,
        execution_mode: draft.execution_mode,
        delay_between: draft.delay_between,
        retries: draft.retries,
        action_items: cloneItems(draft.action_items),
        before_items: cloneItems(draft.before_items),
        error_items: cloneItems(draft.error_items),
        // Сбрасываем legacy single-hook поля.
        before_action_id: '',
        before_code: '',
        before_input: {},
        error_action_id: '',
        error_code: '',
        error_input: {},
    })
    emit('update:open', false)
}

// ── Actions loading ────────────────────────────────────────────────────────────

const actions = ref<Action[]>([])
const loadingActions = ref(false)
const fieldsCache = ref<Map<string, ActionInputField[]>>(new Map())
const actionCache = ref<Map<string, Action>>(new Map())

async function loadActions(): Promise<void> {
    if (actions.value.length) return
    loadingActions.value = true
    try {
        const qs = new URLSearchParams({ 'page[size]': '200' })
        actions.value = await actionRepository.list(qs)
        for (const a of actions.value) {
            actionCache.value.set(a.id, a)
            fieldsCache.value.set(a.id, a.input_fields)
        }
    } catch { /* ignore */ }
    finally { loadingActions.value = false }
}

async function loadFields(id: string): Promise<ActionInputField[]> {
    if (!id) return []
    if (fieldsCache.value.has(id)) return fieldsCache.value.get(id)!
    return []
}

// pickerTarget = `item:${id}` для любого ActionItem (в action_items / before_items / error_items).
const pickerTarget = ref<`item:${string}` | null>(null)
const pickerOpen = computed({
    get: () => pickerTarget.value !== null,
    set: (v: boolean) => { if (!v) pickerTarget.value = null },
})

function openPicker(target: `item:${string}`): void {
    pickerTarget.value = target
}

function findItemById(id: string): ActionItem | undefined {
    return draft.action_items.find(i => i.id === id)
        ?? draft.before_items.find(i => i.id === id)
        ?? draft.error_items.find(i => i.id === id)
}

function pickerSelectedId(): string {
    if (pickerTarget.value?.startsWith('item:')) {
        const id = pickerTarget.value.slice(5)
        return findItemById(id)?.action_id ?? ''
    }
    return ''
}

function onPickerSelect(action: Action): void {
    const target = pickerTarget.value
    actionCache.value.set(action.id, action)
    fieldsCache.value.set(action.id, action.input_fields)
    if (target?.startsWith('item:')) {
        const id = target.slice(5)
        const item = findItemById(id)
        if (item) onItemActionChange(item, action)
    }
}

function actionLabel(id: string, placeholder: string): string {
    if (!id) return placeholder
    return actionCache.value.get(id)?.name ?? id
}

function actionCode(id: string): string {
    return actionCache.value.get(id)?.code ?? ''
}

onMounted(loadActions)

// ── Variables panel ────────────────────────────────────────────────────────────

const copiedVarId = ref<string | null>(null)

async function copyVar(varRef: string, id: string): Promise<void> {
    await navigator.clipboard.writeText(varRef)
    copiedVarId.value = id
    setTimeout(() => { copiedVarId.value = null }, 1500)
}

const blockIds = computed(() => {
    const seen = new Set<string>()
    return props.variables
        .map(v => v.blockId)
        .filter(id => { if (seen.has(id)) return false; seen.add(id); return true })
})

function varsForBlock(blockId: string): VariableItem[] {
    return props.variables.filter(v => v.blockId === blockId)
}

function blockTitle(blockId: string): string {
    return props.variables.find(v => v.blockId === blockId)?.blockTitle ?? blockId
}

// ── Action items ───────────────────────────────────────────────────────────────

function uid(): string {
    return `ai_${Math.random().toString(36).slice(2, 10)}`
}

function newItem(): ActionItem {
    return { id: uid(), code: '', action_id: '', name: '', input: {}, retries: 1 }
}

function addItem(): void {
    draft.action_items.push(newItem())
}

function removeItem(id: string): void {
    const idx = draft.action_items.findIndex(i => i.id === id)
    if (idx !== -1) draft.action_items.splice(idx, 1)
}

function addBeforeItem(): void { draft.before_items.push(newItem()) }
function addErrorItem(): void { draft.error_items.push(newItem()) }
function removeBeforeItem(id: string): void {
    const idx = draft.before_items.findIndex(i => i.id === id)
    if (idx !== -1) draft.before_items.splice(idx, 1)
}
function removeErrorItem(id: string): void {
    const idx = draft.error_items.findIndex(i => i.id === id)
    if (idx !== -1) draft.error_items.splice(idx, 1)
}

function buildInputDefaults(fields: ActionInputField[]): Record<string, unknown> {
    const result: Record<string, unknown> = {}
    for (const f of fields) result[f.key] = ''
    return result
}

function onItemActionChange(item: ActionItem, action: Action): void {
    item.action_id = action.id
    if (!item.code) item.code = action.code || action.key
    if (!item.name) item.name = action.name
    item.input = buildInputDefaults(action.input_fields)
}

function itemFields(item: ActionItem): ActionInputField[] {
    return fieldsCache.value.get(item.action_id) ?? []
}

</script>

<template>
    <AppEditorDrawer
        :open="open"
        title="Настройки действия"
        description="Конфигурация actions для ноды"
        width-class="!w-[680px] !max-w-[680px]"
        icon-bg-class="bg-violet-100"
        :can-save="editable"
        :show-footer="editable"
        @update:open="emit('update:open', $event)"
        @cancel="emit('update:open', false)"
        @save="save"
    >
        <template #icon><Zap class="size-3.5 text-violet-600" /></template>

        <div class="flex min-h-0 flex-1 overflow-hidden">
                <!-- Left: Variables -->
                <div class="w-[190px] shrink-0 overflow-y-auto border-r border-slate-100 px-3 py-4 space-y-4">
                    <div class="px-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Переменные</div>

                    <div class="space-y-0.5">
                        <div class="mb-1.5 px-2 text-[10px] font-medium text-slate-400">Пользователь</div>
                        <button
                            v-for="v in USER_VARIABLES" :key="v.id" type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-50"
                            @click="copyVar(v.name, v.id)"
                        >
                            <span class="truncate font-mono text-[10px] text-slate-500">{{ v.name }}</span>
                            <Check v-if="copiedVarId === v.id" class="size-3 shrink-0 text-emerald-500" />
                            <Copy v-else class="size-3 shrink-0 text-slate-300" />
                        </button>
                    </div>

                    <template v-for="blockId in blockIds" :key="blockId">
                        <div class="space-y-0.5">
                            <div class="mb-1 px-2 text-[10px] font-medium text-slate-500">{{ blockTitle(blockId) }}</div>
                            <button
                                v-for="v in varsForBlock(blockId)" :key="v.id" type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-50"
                                @click="copyVar(v.varRef, v.id)"
                            >
                                <span class="truncate font-mono text-[10px] text-slate-500">{{ v.varRef }}</span>
                                <Check v-if="copiedVarId === v.id" class="size-3 shrink-0 text-emerald-500" />
                                <Copy v-else class="size-3 shrink-0 text-slate-300" />
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Right: Editor -->
                <FormBody class="min-w-0 flex-1">
                    <FormRow>
                        <FormField label="ID ноды">
                            <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
                                {{ nodeId || '—' }}
                            </p>
                        </FormField>
                        <FormField label="Переменная">
                            <Input v-model="draft.variable" :disabled="!editable" class="font-mono" placeholder="action_result" />
                        </FormField>
                    </FormRow>

                    <FormToggle
                        v-model="draft.skipInSurvey"
                        label="Пропустить в опросе"
                        description="Выполняется на бэкенде без отображения пользователю"
                        :disabled="!editable"
                    />

                    <FormToggle
                        v-model="draft.wait_for_result"
                        label="Дождаться результата"
                        description="Выполнить action синхронно и сохранить результат в контекст (доступен как {{ code.result }})"
                        :disabled="!editable"
                    />

                    <FormField label="Режим выполнения">
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border py-2.5 text-sm font-medium transition"
                                :class="draft.execution_mode === 'sequential' ? 'border-violet-400 bg-violet-50 text-violet-700' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'"
                                :disabled="!editable" @click="draft.execution_mode = 'sequential'"
                            >
                                <span class="flex gap-0.5">
                                    <span class="h-2 w-2 rounded-sm bg-current opacity-80" />
                                    <span class="h-2 w-1 rounded-sm bg-current opacity-30" />
                                    <span class="h-2 w-1 rounded-sm bg-current opacity-10" />
                                </span>
                                Последовательно
                            </button>
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border py-2.5 text-sm font-medium transition"
                                :class="draft.execution_mode === 'parallel' ? 'border-violet-400 bg-violet-50 text-violet-700' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'"
                                :disabled="!editable" @click="draft.execution_mode = 'parallel'"
                            >
                                <span class="flex gap-0.5">
                                    <span class="h-2 w-1 rounded-sm bg-current opacity-80" />
                                    <span class="h-2 w-1 rounded-sm bg-current opacity-80" />
                                    <span class="h-2 w-1 rounded-sm bg-current opacity-80" />
                                </span>
                                Параллельно
                            </button>
                        </div>
                    </FormField>

                    <FormRow>
                        <FormField label="Задержка (сек)">
                            <Input
                                :model-value="draft.delay_between ?? ''" type="number" min="0" step="1"
                                :disabled="!editable" placeholder="—"
                                @update:model-value="(v: string | number | undefined) => draft.delay_between = v === '' || v === undefined || v === null ? null : Number(v)"
                            />
                        </FormField>
                        <FormField label="Попыток на действие">
                            <Input
                                :model-value="draft.retries" type="number" min="1" max="10" step="1"
                                :disabled="!editable"
                                @update:model-value="(v: string | number | undefined) => draft.retries = Math.max(1, Number(v) || 1)"
                            />
                        </FormField>
                    </FormRow>

                    <Separator class="bg-slate-100" />

                    <!-- Before hooks (несколько) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <PlayCircle class="size-4 shrink-0 text-emerald-500" />
                                <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">При подготовке (before)</Label>
                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-400">опционально</span>
                            </div>
                            <button v-if="editable" type="button" class="flex items-center gap-1 rounded-lg px-2.5 py-1 text-[12px] font-medium text-emerald-700 ring-1 ring-emerald-200 transition hover:bg-emerald-50" @click="addBeforeItem">
                                <Plus class="size-3" />
                                Добавить
                            </button>
                        </div>

                        <p v-if="!draft.before_items.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-3 text-center text-xs text-slate-400">
                            Нет before-действий
                        </p>

                        <div class="space-y-2">
                            <ActionItemCard
                                v-for="(item, idx) in draft.before_items"
                                :key="item.id"
                                :item="item"
                                :index="idx"
                                variant="before"
                                :editable="editable"
                                :loading-actions="loadingActions"
                                :fields="itemFields(item)"
                                :action-label="actionLabel"
                                :action-code="actionCode"
                                @remove="removeBeforeItem"
                                @open-picker="openPicker"
                            />
                        </div>
                    </div>

                    <Separator class="bg-slate-100" />

                    <!-- Action items list -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Действия</Label>
                            <button v-if="editable" type="button" class="flex items-center gap-1 rounded-lg px-2.5 py-1 text-[12px] font-medium text-violet-600 ring-1 ring-violet-200 transition hover:bg-violet-50" @click="addItem">
                                <Plus class="size-3" />
                                Добавить
                            </button>
                        </div>

                        <p v-if="!draft.action_items.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-center text-sm text-slate-400">
                            Нет добавленных действий
                        </p>

                        <div class="space-y-2">
                            <ActionItemCard
                                v-for="(item, idx) in draft.action_items"
                                :key="item.id"
                                :item="item"
                                :index="idx"
                                variant="main"
                                :editable="editable"
                                :loading-actions="loadingActions"
                                :fields="itemFields(item)"
                                :action-label="actionLabel"
                                :action-code="actionCode"
                                @remove="removeItem"
                                @open-picker="openPicker"
                            />
                        </div>
                    </div>

                    <Separator class="bg-slate-100" />

                    <!-- Error hooks (несколько) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <AlertCircle class="size-4 shrink-0 text-red-500" />
                                <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">При ошибке (on_error)</Label>
                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-400">опционально</span>
                            </div>
                            <button v-if="editable" type="button" class="flex items-center gap-1 rounded-lg px-2.5 py-1 text-[12px] font-medium text-red-600 ring-1 ring-red-200 transition hover:bg-red-50" @click="addErrorItem">
                                <Plus class="size-3" />
                                Добавить
                            </button>
                        </div>

                        <p v-if="!draft.error_items.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-3 text-center text-xs text-slate-400">
                            Нет error-действий
                        </p>

                        <div class="space-y-2">
                            <ActionItemCard
                                v-for="(item, idx) in draft.error_items"
                                :key="item.id"
                                :item="item"
                                :index="idx"
                                variant="error"
                                :editable="editable"
                                :loading-actions="loadingActions"
                                :fields="itemFields(item)"
                                :action-label="actionLabel"
                                :action-code="actionCode"
                                @remove="removeErrorItem"
                                @open-picker="openPicker"
                            />
                        </div>
                    </div>
                </FormBody>
            </div>

    </AppEditorDrawer>

    <ActionPickerDialog
        :open="pickerOpen"
        :selected-id="pickerSelectedId()"
        @update:open="pickerOpen = $event"
        @select="onPickerSelect"
    />
</template>
