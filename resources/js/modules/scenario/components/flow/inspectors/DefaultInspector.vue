<script setup>
import {Label} from '@/components/ui/label'

defineProps({
  node: {type: Object, required: true},
})
</script>

<template>
  <div class="space-y-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ID ноды</Label>
    <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
      {{ node.id }}
    </p>
  </div>
</template>
