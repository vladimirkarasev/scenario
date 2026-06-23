<script setup>
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import { ref } from 'vue'
import { Check, Copy, Eye, Layers } from 'lucide-vue-next'
import { Label } from '@/components/ui/label'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'

defineProps({
    node:                { type: Object,  required: true },
    draft:               { type: Object,  required: true },
    editable:            { type: Boolean, default: false },
    // Список нод сценария — для группировки переменных по блокам.
    nodes:               { type: Array,   default: () => [] },
    inspectorVariables:  { type: Array,   default: () => [] },
})

const emit = defineEmits(['sync'])

const SYSTEM_VARIABLES = [
    { id: 'run_number_formatted', name: 'run_number_formatted', label: 'Номер опроса (с нулями)' },
    { id: 'run_number',           name: 'run_number',           label: 'Номер опроса' },
    { id: 'run_created_at',       name: 'run_created_at',       label: 'Дата запуска' },
    { id: 'run_completed_at',     name: 'run_completed_at',     label: 'Дата завершения' },
    { id: 'run_id',               name: 'run_id',               label: 'ID опроса' },
    { id: 'operator_login',       name: 'operator_login',       label: 'Логин оператора' },
    { id: 'operator_name',        name: 'operator_name',        label: 'Имя оператора' },
    { id: 'operator_fio',         name: 'operator_fio',         label: 'ФИО оператора' },
    { id: 'project_name',         name: 'project_name',         label: 'Проект' },
]

const USER_VARIABLES = [
    { id: 'user.name',      name: 'user.name',      label: 'Имя' },
    { id: 'user.fio',       name: 'user.fio',       label: 'ФИО' },
    { id: 'user.email',     name: 'user.email',     label: 'Email' },
    { id: 'user.phone',     name: 'user.phone',     label: 'Телефон' },
    { id: 'user.auth_date', name: 'user.auth_date', label: 'Дата авторизации' },
]

const copiedId = ref(null)
async function copy(text, id) {
    await navigator.clipboard.writeText(text)
    copiedId.value = id
    setTimeout(() => { copiedId.value = null }, 1500)
}
function copyVar(v)     { void copy(`{{ ${v.name} }}`, v.id) }
function copyBlockVar(v) { void copy(v.varRef, v.id) }

const activeTab = ref('editor')
</script>

<template>
    <div class="flex h-full min-h-0">
        <!-- Variables sidebar -->
        <aside class="flex w-52 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
            <div class="flex-1 space-y-3 overflow-y-auto p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Переменные</div>

                <div class="space-y-0.5">
                    <div class="mb-1.5 text-[10px] font-medium text-slate-400">Системные</div>
                    <button
                        v-for="v in SYSTEM_VARIABLES"
                        :key="v.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-50"
                        @click="copyVar(v)"
                    >
                        <span class="truncate font-mono text-[10px] text-slate-500">{{ v.name }}</span>
                        <Check v-if="copiedId === v.id" class="size-3 shrink-0 text-emerald-500" />
                        <Copy v-else class="size-3 shrink-0 text-slate-300" />
                    </button>
                </div>

                <div class="space-y-0.5">
                    <div class="mb-1.5 text-[10px] font-medium text-slate-400">Пользователь</div>
                    <button
                        v-for="v in USER_VARIABLES"
                        :key="v.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-50"
                        @click="copyVar(v)"
                    >
                        <span class="truncate font-mono text-[10px] text-slate-500">{{ v.name }}</span>
                        <Check v-if="copiedId === v.id" class="size-3 shrink-0 text-emerald-500" />
                        <Copy v-else class="size-3 shrink-0 text-slate-300" />
                    </button>
                </div>

                <template v-if="inspectorVariables.length">
                    <template
                        v-for="block in nodes.filter((n) => n.type === 'block' && inspectorVariables.some((vv) => vv.blockId === n.id))"
                        :key="block.id"
                    >
                        <div class="space-y-0.5">
                            <div class="mb-1.5 truncate text-[10px] font-medium text-slate-500">
                                {{ block.data?.title || block.id }}
                            </div>
                            <button
                                v-for="v in inspectorVariables.filter((vv) => vv.blockId === block.id)"
                                :key="v.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-left transition hover:bg-slate-50"
                                @click="copyBlockVar(v)"
                            >
                                <span class="truncate font-mono text-[10px] text-slate-500">{{ v.varRef }}</span>
                                <Check v-if="copiedId === v.id" class="size-3 shrink-0 text-emerald-500" />
                                <Copy v-else class="size-3 shrink-0 text-slate-300" />
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </aside>

        <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <!-- Tab bar -->
            <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-2.5">
                <div class="inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-[13px] font-medium">
                    <button
                        v-for="tab in [{ key: 'editor', label: 'Редактор', icon: Layers }, { key: 'preview', label: 'Предпросмотр', icon: Eye }]"
                        :key="tab.key"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1 transition"
                        :class="activeTab === tab.key
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="size-3.5" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto bg-slate-50">
                <!-- Editor -->
                <div v-show="activeTab === 'editor'" class="mx-auto max-w-2xl space-y-2 px-6 py-6">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ID ноды</Label>
                        <p class="mt-1 select-all font-mono text-[12px] text-slate-700">{{ node.id }}</p>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <TiptapTextEditor
                            v-model="draft.description"
                            format="html"
                            :editable="editable"
                            min-height="min-h-32"
                            placeholder="Что показать пользователю после завершения опроса?"
                            @update:model-value="emit('sync')"
                        />
                    </div>
                </div>

                <!-- Preview -->
                <div v-if="activeTab === 'preview'" class="mx-auto max-w-2xl px-6 py-6">
                    <div class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]">
                        <div class="h-[2px] bg-gradient-to-r from-emerald-400 to-emerald-500" />
                        <div class="px-5 py-5 text-left">
                            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100">
                                <Check class="size-5 text-emerald-600" />
                            </div>
                            <TiptapTextRenderer
                                v-if="draft.description"
                                :html="draft.description"
                                class="prose prose-sm max-w-none text-[12.5px] text-slate-700"
                            />
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-400">
                        Переменные показаны как есть — будут подставлены во время прохождения опроса.
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
