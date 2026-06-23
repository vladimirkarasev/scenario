<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { History, RotateCcw } from 'lucide-vue-next'

defineProps<{
    revisions: { id: string; created_at: string | null }[]
    formatDateTime: (iso: string | null) => string
}>()
</script>

<template>
    <Card class="border-border/60">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm">История сохранений</CardTitle>
                <Badge variant="secondary">{{ revisions.length }}</Badge>
            </div>
        </CardHeader>
        <CardContent class="p-0">
            <div
                v-if="!revisions.length"
                class="px-5 py-10 text-center text-sm text-muted-foreground"
            >
                Нет сохранений
            </div>
            <div class="divide-y divide-border">
                <div
                    v-for="rev in revisions"
                    :key="rev.id"
                    class="flex items-center gap-3 px-5 py-3.5"
                >
                    <div class="flex size-7 flex-none items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <History class="size-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-foreground">Сохранение #{{ rev.id }}</div>
                        <div class="text-xs text-muted-foreground">{{ formatDateTime(rev.created_at) }}</div>
                    </div>
                    <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs text-muted-foreground">
                        <RotateCcw class="size-3" />
                        Восстановить
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
