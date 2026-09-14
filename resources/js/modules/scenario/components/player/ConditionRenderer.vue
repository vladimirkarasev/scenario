<script setup lang="ts">
import {computed, ref} from 'vue'
import {ExternalLink} from 'lucide-vue-next'
import {Button} from '@/components/ui/button'
import {isSafeHtmlUrl} from '@/lib/safe-html'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {conditionAnswerIcon} from '@/modules/scenario/lib/condition-answer-icons'
import {hasTiptapDocumentContent} from '@/modules/scenario/lib/tiptap-gutenberg-doc'
import type {ScenarioRenderedConditionOption} from '@/modules/scenario/lib/scenario-player-types'

const props = defineProps<{
  question: string
  hideTitle?: boolean
  content?: unknown
  options: ScenarioRenderedConditionOption[]
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [targetNodeId: string]
}>()

const safeOptions = computed(() => (props.options ?? []).filter((option) =>
  Boolean(option?.targetNodeId) || Boolean(option?.url && isSafeHtmlUrl(option.url)),
))
const hasContent = computed(() => hasTiptapDocumentContent(props.content))

const selected = ref<string | null>(null)

function pick(targetNodeId: string): void {
  selected.value = targetNodeId
  emit('select', targetNodeId)
}
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div v-if="!hideTitle" class="border-b border-slate-100 px-6 py-5">
      <h2 class="text-[18px] font-semibold leading-snug text-slate-900">
        {{ question || 'Выберите вариант' }}
      </h2>
    </div>

    <div v-if="hasContent" class="border-b border-slate-100 px-6 py-5">
      <TiptapTextRenderer :document="content" />
    </div>

    <div class="grid grid-cols-2 gap-2 px-6 py-5">
      <div
          v-if="!safeOptions.length"
          class="col-span-2 rounded-xl border border-dashed border-slate-200 px-4 py-3 text-[13px] text-slate-400"
      >
        Для этого условия не настроены кнопки.
      </div>

      <template v-for="(option, index) in safeOptions" :key="option.targetNodeId ?? option.url ?? `option-${index}`">
        <a
            v-if="option.url"
            :href="option.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
            :class="option.width === 'half' ? 'col-span-1' : 'col-span-2'"
        >
          <component :is="conditionAnswerIcon(option.icon)" v-if="conditionAnswerIcon(option.icon)" class="size-3.5" />
          {{ option.label }}
          <ExternalLink class="size-3" />
        </a>

        <Button
            v-else
            type="button"
            :disabled="loading"
            variant="outline"
            class="min-h-9 w-full gap-1.5 rounded-lg px-3 text-xs"
            :class="[
              option.width === 'half' ? 'col-span-1' : 'col-span-2',
              selected === option.targetNodeId
                ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700',
            ]"
            @click="option.targetNodeId && pick(option.targetNodeId)"
        >
          <component :is="conditionAnswerIcon(option.icon)" v-if="conditionAnswerIcon(option.icon)" class="size-3.5" />
          {{ option.label }}
        </Button>
      </template>
    </div>
  </div>
</template>
