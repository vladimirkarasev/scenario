<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {hasTiptapDocumentContent} from '@/modules/scenario/lib/tiptap-gutenberg-doc'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

const title = computed(() => props.data.title || 'Условие')
const hasContent = computed(() => hasTiptapDocumentContent(props.data.content))
</script>

<template>
  <div class="scenario-flow-node relative">
    <Handle
        id="in"
        type="source"
        :position="Position.Top"
        class="scenario-flow-handle !h-[15%] !w-full"
        connectable-start
        connectable-end
    />
    <Handle
        id="left"
        type="source"
        :position="Position.Left"
        class="scenario-flow-handle !h-full !w-[15%]"
        connectable-start
        connectable-end
    />

    <div class="relative flex items-center justify-center">
      <div
          class="condition-node-shape flex size-[152px] items-center justify-center border-[3px] border-sky-500 bg-slate-800 text-center text-white shadow-[0_10px_30px_rgba(14,165,233,0.16)] transition"
      >
        <div class="w-[68%] space-y-1 overflow-hidden">
          <div class="truncate text-[13px] font-semibold">{{ title }}</div>
          <div v-if="hasContent" class="condition-node-content max-h-11 overflow-hidden text-sky-100/80">
            <TiptapTextRenderer :document="data.content" />
          </div>
          <div v-else-if="data.text" class="line-clamp-2 text-[9px] leading-3 text-sky-100/80">
            {{ data.text }}
          </div>
        </div>
      </div>
    </div>

    <svg
        class="scenario-flow-connector scenario-flow-connector--diamond"
        :class="{'scenario-flow-connector--selected': selected}"
        viewBox="0 0 100 100"
        aria-hidden="true"
    >
      <polygon
          class="scenario-flow-connector-polygon"
          points="50 2, 98 50, 50 98, 2 50"
          vector-effect="non-scaling-stroke"
      />
    </svg>

    <Handle
        id="right"
        type="source"
        :position="Position.Right"
        class="scenario-flow-handle !h-full !w-[15%]"
        connectable-start
        connectable-end
    />
    <Handle
        id="out"
        type="source"
        :position="Position.Bottom"
        class="scenario-flow-handle !h-[15%] !w-full"
        connectable-start
        connectable-end
    />
  </div>
</template>

<style>@import './connector.css';</style>

<style scoped>
.condition-node-shape {
  clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);
}

.condition-node-content :deep(.tiptap-content) {
  color: inherit;
  font-size: 9px;
  line-height: 1.25;
}

.condition-node-content :deep(.tiptap-content *) {
  margin-block: 0.125rem;
}

.condition-node-content :deep(.tiptap-content h1),
.condition-node-content :deep(.tiptap-content h2),
.condition-node-content :deep(.tiptap-content h3),
.condition-node-content :deep(.tiptap-content h4),
.condition-node-content :deep(.tiptap-content h5),
.condition-node-content :deep(.tiptap-content h6) {
  color: inherit;
  font-size: 10px;
  line-height: 1.2;
}
</style>
