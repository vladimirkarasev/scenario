<script setup lang="ts">
import {useDirectoryImport} from '@/modules/directories/composables/useDirectoryImport'
import {Badge} from '@/components/ui/badge'
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Loader2} from 'lucide-vue-next'
import {Skeleton} from '@/components/ui/skeleton'
import {onMounted} from 'vue'

const props = defineProps<{ directoryId: string; versionId: number }>()

function isActive(status: string): boolean {
  return status === 'pending' || status === 'processing'
}

const {
  imports,
  loading,
  loadImports,
  importLabel,
  importVariant
} = useDirectoryImport(props.directoryId, props.versionId)

onMounted(() => {
  void loadImports()
})

defineExpose({reload: () => loadImports()})

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <Card class="border-border/60">
    <CardHeader>
      <CardTitle>История импортов</CardTitle>
      <CardDescription>{{ imports.length }} задания</CardDescription>
    </CardHeader>
    <CardContent>
      <div class="overflow-hidden rounded-xl border border-border/60">
        <div class="overflow-y-auto max-h-96">
          <Table>
            <TableHeader class="sticky top-0 z-10 bg-card">
              <TableRow>
                <TableHead>ID</TableHead>
                <TableHead>Статус</TableHead>
                <TableHead>Версия</TableHead>
                <TableHead>Источник</TableHead>
                <TableHead>Режим</TableHead>
                <TableHead>Строки</TableHead>
                <TableHead>Начало</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-if="loading">
                <TableRow v-for="i in 4" :key="i">
                  <TableCell>
                    <Skeleton class="h-4 w-8"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-5 w-20 rounded-full"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-4 w-8"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-4 w-12"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-5 w-16 rounded-full"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-4 w-16"/>
                  </TableCell>
                  <TableCell>
                    <Skeleton class="h-4 w-28"/>
                  </TableCell>
                </TableRow>
              </template>
              <template v-else>
                <TableRow v-for="imp in imports" :key="imp.id">
                  <TableCell class="font-mono text-xs">#{{ imp.id }}</TableCell>
                  <TableCell>
                    <div class="flex items-center gap-1.5">
                      <Loader2 v-if="isActive(imp.status)" class="size-3 animate-spin text-muted-foreground"/>
                      <Badge :variant="importVariant(imp.status)">{{ importLabel(imp.status) }}</Badge>
                    </div>
                  </TableCell>
                  <TableCell class="font-mono text-xs text-muted-foreground">
                    {{ imp.version_number != null ? `v${imp.version_number}` : '—' }}
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
                  <TableCell class="text-xs text-muted-foreground">{{ fmtDateTime(imp.started_at) }}</TableCell>
                </TableRow>
                <TableRow v-if="!imports.length">
                  <TableCell colspan="7" class="h-24 text-center text-muted-foreground">История пуста.</TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
