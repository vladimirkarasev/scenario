<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ContextMenuLabel } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  inset: { type: Boolean, required: false },
});

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <ContextMenuLabel
    data-slot="context-menu-label"
    :data-inset="inset ? '' : undefined"
    v-bind="delegatedProps"
    :class="
      cn(
        'text-muted-foreground px-1.5 py-1 text-xs font-medium data-inset:pl-7',
        props.class,
      )
    "
  >
    <slot />
  </ContextMenuLabel>
</template>
