<script setup>
import {ref} from 'vue'
import {Textarea} from '@/components/ui/textarea'
import {Check, Copy, Pencil, X} from 'lucide-vue-next'

const props = defineProps({
  schemaPreview: {type: String, default: ''},
  editable: {type: Boolean, default: false},
})

const emit = defineEmits(['apply'])

const jsonEditMode = ref(false)
const jsonDraft = ref('')
const jsonError = ref('')
const copied = ref(false)

async function copyJson() {
  await navigator.clipboard.writeText(props.schemaPreview)
  copied.value = true
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

function startJsonEdit() {
  jsonDraft.value = props.schemaPreview
  jsonError.value = ''
  jsonEditMode.value = true
}

function cancelJsonEdit() {
  jsonEditMode.value = false
  jsonError.value = ''
  jsonDraft.value = ''
}

function applyJsonEdit() {
  let parsed
  try {
    parsed = JSON.parse(jsonDraft.value)
  } catch (e) {
    jsonError.value = e instanceof Error ? e.message : 'Невалидный JSON'
    return
  }

  if (parsed && Array.isArray(parsed.edges)) {
    parsed = {
      ...parsed,
      connections: parsed.edges.map((e) => ({
        id: e.id,
        source: {blockId: e.source, port: e.sourceHandle ?? null},
        target: {blockId: e.target, port: e.targetHandle ?? null},
        label: e.label ?? null,
        data: e.data ?? {},
      })),
    }
    delete parsed.edges
  }

  emit('apply', parsed)
  jsonEditMode.value = false
  jsonError.value = ''
  jsonDraft.value = ''
}
</script>

<template>
  <div class="flex flex-1 min-h-0 flex-col gap-3 bg-white p-4">
    <div class="flex shrink-0 items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <span class="text-[12px] font-semibold text-slate-600">JSON схема</span>
        <span v-if="jsonEditMode"
              class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-600 ring-1 ring-amber-200">
                    редактирование
                </span>
      </div>

      <div class="flex items-center gap-1.5">
        <template v-if="jsonEditMode">
          <button type="button"
                  class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[12px] font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                  @click="cancelJsonEdit">
            <X class="size-3.5"/>
            Отмена
          </button>
          <button type="button"
                  class="inline-flex h-7 items-center gap-1.5 rounded-lg bg-blue-600 px-2.5 text-[12px] font-semibold text-white transition hover:bg-blue-700"
                  @click="applyJsonEdit">
            <Check class="size-3.5"/>
            Применить
          </button>
        </template>
        <template v-else>
          <button type="button"
                  class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[12px] font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                  @click="copyJson">
            <Copy v-if="!copied" class="size-3.5"/>
            <Check v-else class="size-3.5 text-emerald-500"/>
            {{ copied ? 'Скопировано' : 'Копировать' }}
          </button>
          <button v-if="editable" type="button"
                  class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-[12px] font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                  @click="startJsonEdit">
            <Pencil class="size-3.5"/>
            Редактировать
          </button>
        </template>
      </div>
    </div>

    <p v-if="jsonError" class="shrink-0 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200">
      {{ jsonError }}
    </p>

    <Textarea
        v-if="jsonEditMode"
        v-model="jsonDraft"
        class="h-full resize-none overflow-y-auto border-slate-200 font-mono text-xs text-slate-700 field-sizing-fixed"
    />
    <Textarea
        v-else
        :model-value="schemaPreview"
        class="h-full resize-none overflow-y-auto border-slate-200 font-mono text-xs text-slate-700 field-sizing-fixed"
        readonly
    />
  </div>
</template>
