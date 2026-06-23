<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { pageRange } from '@/lib/pagination'

const props = defineProps<{
    currentPage: number
    totalPages: number
    total: number
    perPage: number
}>()

const emit = defineEmits<{ 'update:currentPage': [page: number] }>()

const from = () => (props.currentPage - 1) * props.perPage + 1
const to = () => Math.min(props.currentPage * props.perPage, props.total)
</script>

<template>
    <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-slate-100 px-5 py-3"
    >
        <span class="text-[12px] text-slate-400">{{ from() }}–{{ to() }} из {{ total }}</span>
        <div class="flex items-center gap-1">
            <button
                :disabled="currentPage === 1"
                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                @click="currentPage > 1 && emit('update:currentPage', currentPage - 1)"
            >
                <ChevronLeft :size="13" />
            </button>
            <template v-for="p in pageRange(currentPage, totalPages)" :key="String(p)">
                <span v-if="typeof p === 'string'" class="w-7 text-center text-[12px] text-slate-400">…</span>
                <button
                    v-else
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-[12px] font-medium transition"
                    :class="p === currentPage ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                    @click="emit('update:currentPage', p as number)"
                >
{{ p }}
</button>
            </template>
            <button
                :disabled="currentPage === totalPages"
                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                @click="currentPage < totalPages && emit('update:currentPage', currentPage + 1)"
            >
                <ChevronRight :size="13" />
            </button>
        </div>
    </div>
</template>
