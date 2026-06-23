<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import {
    AVAILABLE_VALIDATION_RULES,
    VALIDATION_RULE_LABELS,
} from '@/modules/scenario/lib/scenario-block-fields'
import type { BlockField, ValidationRule, ValidationRuleType } from '@/modules/scenario/lib/scenario-block-fields'

const props = defineProps<{
    field: BlockField
    disabled?: boolean
}>()

const emit = defineEmits<{
    update: [patch: { validation: ValidationRule[] }]
}>()

defineOptions({ inheritAttrs: false })

const availableTypes = computed<ValidationRuleType[]>(
    () => AVAILABLE_VALIDATION_RULES[props.field.type] ?? [],
)

const rules = computed<ValidationRule[]>(() => props.field.validation ?? [])

const unusedTypes = computed(() =>
    availableTypes.value.filter((t) => !rules.value.some((r) => r.type === t)),
)

const adding = ref(false)
const newType = ref<ValidationRuleType | ''>('')
const newValue = ref('')
const newMessage = ref('')

function openAdd() {
    newType.value = unusedTypes.value[0] ?? ''
    newValue.value = ''
    newMessage.value = ''
    adding.value = true
}

function confirmAdd() {
    if (!newType.value || !newValue.value) return
    const rule: ValidationRule = {
        id: `rule_${Math.random().toString(36).slice(2, 10)}`,
        type: newType.value as ValidationRuleType,
        value: newValue.value,
        message: newMessage.value,
    }
    emit('update', { validation: [...rules.value, rule] })
    adding.value = false
}

function cancelAdd() {
    adding.value = false
}

function updateRule(id: string, patch: Partial<ValidationRule>) {
    emit('update', { validation: rules.value.map((r) => r.id === id ? { ...r, ...patch } : r) })
}

function removeRule(id: string) {
    emit('update', { validation: rules.value.filter((r) => r.id !== id) })
}
</script>

<template>
    <div v-if="availableTypes.length" class="space-y-1">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Валидация</span>

        <div class="relative ml-3 mt-2 space-y-0">
            <!-- Chain steps -->
            <template v-for="(rule, index) in rules" :key="rule.id">
                <!-- Connector line above (except first) -->
                <div v-if="index > 0" class="ml-[7px] h-3 w-px bg-slate-200" />

                <div class="flex items-start gap-2">
                    <!-- Node dot -->
                    <div class="mt-2 size-[15px] shrink-0 rounded-full border-2 border-blue-400 bg-white" />

                    <!-- Rule card -->
                    <div class="flex flex-1 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5">
                        <select
                            :value="rule.type"
                            :disabled="disabled"
                            class="h-6 rounded-md border border-slate-200 bg-slate-50 px-1.5 text-[11px] font-medium outline-none focus:border-ring focus:ring-1 focus:ring-ring disabled:opacity-50"
                            @change="updateRule(rule.id, { type: ($event.target as HTMLSelectElement).value as ValidationRuleType })"
                        >
                            <option
                                v-for="t in availableTypes"
                                :key="t"
                                :value="t"
                                :disabled="t !== rule.type && rules.some((r) => r.type === t)"
                            >
                                {{ VALIDATION_RULE_LABELS[t].label }}
                            </option>
                        </select>

                        <Input
                            :model-value="rule.value"
                            :placeholder="VALIDATION_RULE_LABELS[rule.type].placeholder"
                            :disabled="disabled"
                            class="h-6 w-20 shrink-0 text-[12px]"
                            @update:model-value="updateRule(rule.id, { value: String($event) })"
                        />

                        <Input
                            :model-value="rule.message"
                            placeholder="Сообщение об ошибке"
                            :disabled="disabled"
                            class="h-6 min-w-0 flex-1 text-[12px]"
                            @update:model-value="updateRule(rule.id, { message: String($event) })"
                        />

                        <button
                            type="button"
                            :disabled="disabled"
                            class="flex size-5 shrink-0 items-center justify-center rounded-md text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:opacity-40"
                            @click="removeRule(rule.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                </div>
            </template>

            <!-- Connector before add -->
            <div v-if="rules.length" class="ml-[7px] h-3 w-px bg-slate-200" />

            <!-- Add new rule -->
            <template v-if="!adding">
                <div v-if="unusedTypes.length" class="flex items-center gap-2">
                    <div class="mt-0.5 size-[15px] shrink-0 rounded-full border-2 border-dashed border-slate-300 bg-white" />
                    <button
                        type="button"
                        :disabled="disabled"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:opacity-50"
                        @click="openAdd"
                    >
                        <Plus class="size-3" />
                        Добавить правило
                    </button>
                </div>
            </template>

            <!-- Inline add form -->
            <template v-else>
                <div class="flex items-start gap-2">
                    <div class="mt-2 size-[15px] shrink-0 rounded-full border-2 border-dashed border-blue-300 bg-white" />

                    <div class="flex flex-1 flex-wrap items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/40 px-2.5 py-1.5">
                        <select
                            v-model="newType"
                            class="h-6 rounded-md border border-slate-200 bg-white px-1.5 text-[11px] font-medium outline-none focus:border-ring focus:ring-1 focus:ring-ring"
                        >
                            <option v-for="t in unusedTypes" :key="t" :value="t">
                                {{ VALIDATION_RULE_LABELS[t].label }}
                            </option>
                        </select>

                        <Input
                            v-model="newValue"
                            :placeholder="newType ? VALIDATION_RULE_LABELS[newType as ValidationRuleType].placeholder : 'значение'"
                            class="h-6 w-20 shrink-0 text-[12px]"
                        />

                        <Input
                            v-model="newMessage"
                            placeholder="Сообщение об ошибке"
                            class="h-6 min-w-0 flex-1 text-[12px]"
                        />

                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                :disabled="!newType || !newValue"
                                class="inline-flex h-6 items-center rounded-md bg-blue-600 px-2 text-[11px] font-semibold text-white transition hover:bg-blue-700 disabled:opacity-40"
                                @click="confirmAdd"
                            >
                                Добавить
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-6 items-center rounded-md px-2 text-[11px] text-slate-500 transition hover:bg-slate-100"
                                @click="cancelAdd"
                            >
                                Отмена
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
