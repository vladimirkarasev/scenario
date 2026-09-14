<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {computed, ref} from 'vue'
import {Eye, ExternalLink, GripVertical, Layers, Plus, Trash2} from 'lucide-vue-next'
import {Button} from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {FormActions, FormBody, FormError, FormField, FormInput} from '@/components/form'
import ScenarioVariableList from '@/modules/scenario/components/ScenarioVariableList.vue'
import ConditionRenderer from '@/modules/scenario/components/player/ConditionRenderer.vue'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import NodeTitleSettings from '@/modules/scenario/components/flow/NodeTitleSettings.vue'
import {useConditionAnswerModal} from '@/modules/scenario/composables/useConditionAnswerModal'
import {CONDITION_ANSWER_ICONS, conditionAnswerIcon} from '@/modules/scenario/lib/condition-answer-icons'
import type {ConditionBranch} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioRenderedConditionOption} from '@/modules/scenario/lib/scenario-player-types'
import type {LinkedBlockEntry} from '@/modules/scenario/composables/useLinkedScenarioVariables'
import type {VariableListBlock} from '@/modules/scenario/composables/useScenarioVariables'
import type {ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'

const props = defineProps<{
  node: ScenarioFlowNode
  draft: Record<string, unknown>
  editable: boolean
  variables: VariableEntry[]
  blocks: Array<VariableListBlock | LinkedBlockEntry>
}>()

defineEmits<{
  sync: []
}>()

const answers = computed<ConditionBranch[]>(() => Array.isArray(props.draft.conditionBranches)
  ? props.draft.conditionBranches as ConditionBranch[]
  : Array.isArray(props.draft.options)
    ? props.draft.options as ConditionBranch[]
    : [])
const activeTab = ref<'editor' | 'preview'>('editor')
const previewTitle = computed(() => String(props.draft.title || 'Условие'))
const previewOptions = computed<ScenarioRenderedConditionOption[]>(() => answers.value.map((answer) => ({
  label: answer.label || 'Без текста',
  icon: answer.icon,
  targetNodeId: answer.action === 'transition' ? answer.id : null,
  url: answer.action === 'url' ? answer.url : null,
  width: answer.width,
})))
const selectedAnswerId = ref<string | null>(null)
const draggedAnswerId = ref<string | null>(null)
const dragOverAnswerId = ref<string | null>(null)
const canDrag = ref(false)
const answerModal = useConditionAnswerModal(
  (answerId, values) => updateAnswer(answerId, values),
  (answerId) => removeAnswer(answerId),
)

function createAnswerId(): string {
  return `condition_answer_${Math.random().toString(36).slice(2, 10)}`
}

function answerIconLabel(icon: string | null): string {
  return CONDITION_ANSWER_ICONS.find((item) => item.value === icon)?.label ?? 'Без иконки'
}

function addAnswer(): void {
  const nextPriority = answers.value.reduce(
    (maximum, answer) => Math.max(maximum, Number.isFinite(answer.priority) ? answer.priority : 0),
    0,
  ) + 1
  const answer: ConditionBranch = {
    id: createAnswerId(),
    label: `Ответ ${answers.value.length + 1}`,
    icon: null,
    condition: 'true',
    action: 'transition',
    url: '',
    width: 'full',
    priority: nextPriority,
  }
  const nextAnswers = [
    ...answers.value,
    answer,
  ]
  props.draft.conditionBranches = nextAnswers
  props.draft.options = nextAnswers
  selectedAnswerId.value = answer.id
  answerModal.show(answer)
}

function updateAnswer(answerId: string, patch: Partial<ConditionBranch>): void {
  const nextAnswers = answers.value.map((answer) => answer.id === answerId
    ? {...answer, ...patch}
    : answer)
  props.draft.conditionBranches = nextAnswers
  props.draft.options = nextAnswers
}

function removeAnswer(answerId: string): void {
  const nextAnswers = answers.value.filter((answer) => answer.id !== answerId)
  props.draft.conditionBranches = nextAnswers
  props.draft.options = nextAnswers
  if (selectedAnswerId.value === answerId) {
    selectedAnswerId.value = null
  }
}

function selectAnswer(answerId: string): void {
  const answer = answers.value.find((item) => item.id === answerId)
  if (!answer) {
    return
  }
  selectedAnswerId.value = answerId
  answerModal.show(answer)
}

function startDragging(event: DragEvent, answerId: string): void {
  draggedAnswerId.value = answerId
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
}

function dragOverAnswer(answerId: string): void {
  dragOverAnswerId.value = answerId
}

function dropAnswer(targetAnswerId: string): void {
  const sourceAnswerId = draggedAnswerId.value
  resetDragging()
  if (!sourceAnswerId || sourceAnswerId === targetAnswerId) {
    return
  }

  const reorderedAnswers = [...answers.value]
  const sourceIndex = reorderedAnswers.findIndex((answer) => answer.id === sourceAnswerId)
  const targetIndex = reorderedAnswers.findIndex((answer) => answer.id === targetAnswerId)
  if (sourceIndex < 0 || targetIndex < 0) {
    return
  }

  const [movedAnswer] = reorderedAnswers.splice(sourceIndex, 1)
  if (!movedAnswer) {
    return
  }
  reorderedAnswers.splice(targetIndex, 0, movedAnswer)
  props.draft.conditionBranches = reorderedAnswers
  props.draft.options = reorderedAnswers
}

function resetDragging(): void {
  draggedAnswerId.value = null
  dragOverAnswerId.value = null
  canDrag.value = false
}
</script>

<template>
  <div class="flex h-full min-h-0">
    <aside class="flex w-60 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
      <div class="flex-1 space-y-3 overflow-y-auto p-3">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Переменные</div>
        <ScenarioVariableList :variables="variables" :blocks="blocks" />
      </div>
    </aside>

    <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
      <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-2.5">
        <div class="inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-[13px] font-medium">
          <button
              v-for="tab in [{key: 'editor' as const, label: 'Редактор', icon: Layers}, {key: 'preview' as const, label: 'Предпросмотр', icon: Eye}]"
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
        <div v-show="activeTab === 'editor'" class="mx-auto w-full max-w-4xl space-y-4 px-6 py-6">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="space-y-4 p-4">
              <div class="space-y-1.5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ID ноды</div>
                <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
                  {{ node.id }}
                </p>
              </div>

              <NodeTitleSettings
                  v-model:title="draft.title"
                  v-model:hide-title="draft.hideTitle"
                  :editable="editable"
              />
            </div>
          </div>

          <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <TiptapTextEditor
                v-model="draft.content"
                :editable="editable"
                min-height="min-h-28"
                placeholder="Введите сообщение для пользователя..."
            />

            <div v-if="answers.length" class="mt-3 grid grid-cols-2 gap-2">
              <div
                  v-for="answer in answers"
                  :key="answer.id"
                  class="group relative rounded-lg border border-dashed p-1 pr-7 transition"
                  :draggable="editable && canDrag"
                  :class="[
                    answer.width === 'half' ? 'col-span-1' : 'col-span-2',
                    selectedAnswerId === answer.id
                      ? 'border-sky-400 bg-sky-50/60'
                      : 'border-slate-300 bg-white hover:border-sky-300',
                    dragOverAnswerId === answer.id && draggedAnswerId !== answer.id ? 'ring-2 ring-sky-300' : '',
                    draggedAnswerId === answer.id ? 'opacity-50' : '',
                  ]"
                  @click="selectAnswer(answer.id)"
                  @dragstart="startDragging($event, answer.id)"
                  @dragover.prevent="dragOverAnswer(answer.id)"
                  @drop="dropAnswer(answer.id)"
                  @dragend="resetDragging"
              >
                <Button
                    type="button"
                    variant="ghost"
                    class="min-h-9 w-full justify-center gap-1.5 rounded-md bg-slate-100 px-2.5 text-xs font-medium text-slate-700 group-hover:bg-slate-200/70"
                    :aria-pressed="selectedAnswerId === answer.id"
                >
                  <component :is="conditionAnswerIcon(answer.icon)" v-if="conditionAnswerIcon(answer.icon)" class="size-3.5 shrink-0" />
                  <span class="truncate">{{ answer.label || 'Без текста' }}</span>
                  <ExternalLink v-if="answer.action === 'url'" class="size-3 shrink-0 text-slate-400" />
                </Button>
                <GripVertical
                    class="absolute right-1.5 top-1/2 size-3.5 -translate-y-1/2 cursor-grab text-slate-300 active:cursor-grabbing"
                    @click.stop
                    @mousedown.stop="canDrag = true"
                    @mouseup.stop="canDrag = false"
                />
              </div>
            </div>

            <div v-else class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400">
              Добавьте первый вариант перехода.
            </div>

            <div class="mt-5 flex justify-center">
              <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  class="size-9 rounded-full border-slate-300 text-slate-500 shadow-sm hover:border-sky-400 hover:bg-sky-50 hover:text-sky-600"
                  :disabled="!editable"
                  aria-label="Добавить вариант"
                  @click="addAnswer"
              >
                <Plus class="size-4" />
              </Button>
            </div>
          </section>
        </div>

        <div v-if="activeTab === 'preview'" class="mx-auto max-w-2xl px-6 py-6">
          <ConditionRenderer
              :question="previewTitle"
              :hide-title="Boolean(draft.hideTitle)"
              :content="draft.content"
              :options="previewOptions"
          />

          <div class="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
            <Eye class="size-3" />
            Предпросмотр условия
          </div>
        </div>
      </div>
    </main>

    <Dialog v-model:open="answerModal.open.value">
      <DialogContent class="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
        <DialogHeader class="shrink-0 border-b border-slate-100 px-6 py-4">
          <DialogTitle>Настройки кнопки</DialogTitle>
          <DialogDescription class="sr-only">Настройка текста, иконки и действия кнопки условия</DialogDescription>
        </DialogHeader>

        <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="answerModal.save">
          <FormBody>
            <FormError :message="answerModal.formError.value" />
            <div class="grid gap-4 md:grid-cols-2">
              <FormInput
                  id="condition-answer-label"
                  v-model="answerModal.form.label"
                  name="condition-answer-label"
                  :disabled="!editable"
                  label="Текст кнопки"
                  placeholder="Например: Продолжить"
                  :error="answerModal.errors.label"
              />
              <FormField label="Иконка" for="condition-answer-icon" :error="answerModal.errors.icon">
                <Select
                    :model-value="answerModal.form.icon ?? 'none'"
                    :disabled="!editable"
                    @update:model-value="answerModal.form.icon = $event === 'none' ? null : String($event)"
                >
                  <SelectTrigger id="condition-answer-icon">
                    <SelectValue placeholder="Без иконки">
                      <span class="flex items-center gap-2">
                        <component :is="conditionAnswerIcon(answerModal.form.icon)" v-if="conditionAnswerIcon(answerModal.form.icon)" class="size-4" />
                        <span>{{ answerIconLabel(answerModal.form.icon) }}</span>
                      </span>
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Без иконки</SelectItem>
                    <SelectItem v-for="item in CONDITION_ANSWER_ICONS" :key="item.value" :value="item.value">
                      <span class="flex items-center gap-2">
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                      </span>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </FormField>

              <FormField label="Ширина кнопки" for="condition-answer-width" :error="answerModal.errors.width">
                <Select
                    :model-value="answerModal.form.width"
                    :disabled="!editable"
                    @update:model-value="answerModal.form.width = $event === 'half' ? 'half' : 'full'"
                >
                  <SelectTrigger id="condition-answer-width">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="full">На всю строку</SelectItem>
                    <SelectItem value="half">Половина строки</SelectItem>
                  </SelectContent>
                </Select>
              </FormField>

              <FormField label="Действие" for="condition-answer-action" :error="answerModal.errors.action">
                <Select
                    :model-value="answerModal.form.action"
                    :disabled="!editable"
                    @update:model-value="answerModal.form.action = $event === 'url' ? 'url' : 'transition'"
                >
                  <SelectTrigger id="condition-answer-action">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="transition">Переход по стрелке</SelectItem>
                    <SelectItem value="url">Открыть ссылку</SelectItem>
                  </SelectContent>
                </Select>
              </FormField>

              <FormInput
                  id="condition-answer-priority"
                  :model-value="answerModal.form.priority"
                  name="condition-answer-priority"
                  :disabled="!editable"
                  label="Приоритет обработки"
                  type="number"
                  hint="Меньшее число обрабатывается раньше"
                  :error="answerModal.errors.priority"
                  @update:model-value="answerModal.form.priority = Number($event)"
              />

              <div class="md:col-span-2">
                <FormInput
                    id="condition-answer-expression"
                    v-model="answerModal.form.condition"
                    name="condition-answer-expression"
                    :disabled="!editable"
                    label="Условие показа кнопки"
                    placeholder="Например: {{ age >= 18 }}, true или {{ isElse() }}"
                    :error="answerModal.errors.condition"
                />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="mt-2"
                    :disabled="!editable"
                    @click="answerModal.form.condition = '{{ isElse() }}'"
                >
                  Использовать «иначе»
                </Button>
              </div>

              <FormInput
                  v-if="answerModal.form.action === 'url'"
                  id="condition-answer-url"
                  v-model="answerModal.form.url"
                  name="condition-answer-url"
                  :disabled="!editable"
                  class="md:col-span-2"
                  label="Ссылка"
                  type="url"
                  autocomplete="url"
                  placeholder="https://example.com"
                  :error="answerModal.errors.url"
              />
              <p v-else class="text-xs leading-5 text-slate-400 md:col-span-2">
                Справа от этой кнопки на node будет отдельная точка перехода.
              </p>
            </div>
          </FormBody>

          <FormActions
              class="shrink-0"
              :submitting="answerModal.submitting.value"
              submit-label="Готово"
              cancel-label="Отмена"
              @cancel="answerModal.close"
          >
            <template #extra>
              <Button
                  type="button"
                  variant="ghost"
                  class="mr-auto text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                  :disabled="!editable"
                  @click="answerModal.remove"
              >
                <Trash2 class="size-4" />
                Удалить
              </Button>
            </template>
          </FormActions>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
