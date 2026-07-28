<script setup lang="ts">
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Badge} from '@/components/ui/badge'
import {Loader2} from 'lucide-vue-next'
import type {useDirectoryImport} from '@/modules/directories/composables/useDirectoryImport'

defineProps<{
  impCtx: ReturnType<typeof useDirectoryImport>
  formatDateTime: (iso: string | null) => string
}>()
</script>

<template>
  <Card class="border-border/60">
    <CardHeader>
      <CardTitle>История импортов</CardTitle>
      <CardDescription>{{ impCtx.imports.value.length }} задания</CardDescription>
    </CardHeader>
    <CardContent>
      <div class="overflow-hidden rounded-xl border border-border/60">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>Статус</TableHead>
              <TableHead>Источник</TableHead>
              <TableHead>Режим</TableHead>
              <TableHead>Строки</TableHead>
              <TableHead>Начало</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="impCtx.loading.value">
              <TableCell colspan="6" class="h-24 text-center text-muted-foreground">
                <Loader2 class="inline size-4 animate-spin mr-2"/>
                Загрузка...
              </TableCell>
            </TableRow>
            <TableRow v-for="imp in impCtx.imports.value" v-else :key="imp.id">
              <TableCell class="font-mono text-xs">#{{ imp.id }}</TableCell>
              <TableCell>
                <Badge :variant="impCtx.importVariant(imp.status)">{{ impCtx.importLabel(imp.status) }}</Badge>
              </TableCell>
              <TableCell class="text-sm">
                {{ imp.source_type === 'proxy' ? 'Proxy' : 'Excel' }}
              </TableCell>
              <TableCell>
                <Badge variant="outline">{{ imp.mode }}</Badge>
              </TableCell>
              <TableCell class="text-sm">
                <span class="text-emerald-400">{{ imp.imported_rows }}</span>
                <span class="text-muted-foreground">/{{ imp.processed_rows }}</span>
                <span v-if="imp.failed_rows" class="ml-1 text-destructive">({{ imp.failed_rows }} err)</span>
              </TableCell>
              <TableCell class="text-xs text-muted-foreground">{{ formatDateTime(imp.started_at) }}</TableCell>
            </TableRow>
            <TableRow v-if="!impCtx.loading.value && !impCtx.imports.value.length">
              <TableCell colspan="6" class="h-24 text-center text-muted-foreground">История пуста.</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </CardContent>
  </Card>
</template>
