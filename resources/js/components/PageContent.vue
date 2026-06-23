<script setup>
defineProps({
    contentClass: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="flex h-full w-full flex-col">
        <slot name="bars" />
        <div class="flex-1 overflow-auto" :class="contentClass">
            <slot />
        </div>
    </div>
</template>
