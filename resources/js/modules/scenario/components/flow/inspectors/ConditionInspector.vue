<script setup>
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Check, Copy } from 'lucide-vue-next'
import { USER_VARIABLES } from '@/modules/scenario/lib/scenario-flow-constants'

defineProps({
    node: { type: Object, required: true },
    draft: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    nodes: { type: Array, default: () => [] },
    inspectorVariables: { type: Array, default: () => [] },
    copiedInspectorVariableId: { type: String, default: null },
})

defineEmits(['sync', 'copy-user-variable', 'copy-variable'])
</script>

<template>
    <div class="flex h-full min-h-0">
        <aside class="flex w-56 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
            <div class="flex-1 space-y-3 overflow-y-auto p-3">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Переменные</div>

                <div class="space-y-0.5">
                    <div class="mb-1 text-[10px] font-medium text-slate-500">Пользователь</div>
                    <button
                        v-for="variable in USER_VARIABLES"
                        :key="variable.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition hover:bg-slate-50"
                        @click="$emit('copy-user-variable', variable)"
                    >
                        <span class="truncate font-mono text-[10px] text-slate-500">{{ variable.name }}</span>
                        <Check v-if="copiedInspectorVariableId === variable.id" class="size-3 shrink-0 text-emerald-500" />
                        <Copy v-else class="size-3 shrink-0 text-slate-300" />
                    </button>
                </div>

                <template v-if="inspectorVariables.length">
                    <template
                        v-for="block in nodes.filter((n) => n.type === 'block' && inspectorVariables.some((v) => v.blockId === n.id))"
                        :key="block.id"
                    >
                        <div class="space-y-0.5">
                            <div class="mb-1 truncate text-[10px] font-medium text-slate-500">
                                {{ block.data?.title || block.id }}
                            </div>
                            <button
                                v-for="variable in inspectorVariables.filter((v) => v.blockId === block.id)"
                                :key="variable.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition hover:bg-slate-50"
                                @click="$emit('copy-variable', variable)"
                            >
                                <span class="truncate font-mono text-[10px] text-slate-500">{{ variable.varRef }}</span>
                                <Check v-if="copiedInspectorVariableId === variable.id" class="size-3 shrink-0 text-emerald-500" />
                                <Copy v-else class="size-3 shrink-0 text-slate-300" />
                            </button>
                        </div>
                    </template>
                </template>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto bg-slate-50 py-8">
            <div class="mx-auto max-w-5xl w-full space-y-2.5 px-6">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="space-y-4 p-4">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ID ноды</Label>
                            <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
                                {{ node.id }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="node-value" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Значение</Label>
                            <Textarea
                                id="node-value"
                                v-model="draft.value"
                                rows="4"
                                class="border-slate-200 font-mono text-sm"
                                :disabled="!editable"
                                @update:model-value="$emit('sync')"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
