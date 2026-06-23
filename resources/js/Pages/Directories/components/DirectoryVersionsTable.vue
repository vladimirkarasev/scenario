<script setup lang="ts">
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Link} from '@inertiajs/vue3'
import {Loader2, Pencil, Plus, Star, Trash2} from 'lucide-vue-next'
import type {DirectoryVersion} from '@/modules/directories/types/directory'

defineProps<{
  versions: DirectoryVersion[]
  loading: boolean
  directoryId: string
  canManage: boolean
  canDelete: boolean
  formatDateTime: (iso: string | null) => string
}>()

defineEmits<{
  createVersion: []
  activate: [version: DirectoryVersion]
  remove: [version: DirectoryVersion]
}>()
</script>

<template>
  <Card class="border-border/60">
    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <CardTitle>Версии справочника</CardTitle>
        <CardDescription>Каждая версия — отдельный набор данных. Активная используется по умолчанию.</CardDescription>
      </div>
      <Button v-if="canManage" size="sm" class="gap-2 shrink-0" @click="$emit('createVersion')">
        <Plus class="size-4"/>
        Создать версию
      </Button>
    </CardHeader>
    <CardContent>
      <div class="overflow-hidden rounded-xl border border-border/60">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Версия</TableHead>
              <TableHead>Записей</TableHead>
              <TableHead>Импортов</TableHead>
              <TableHead>Создана</TableHead>
              <TableHead class="text-right">Действия</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="loading">
              <TableCell colspan="5" class="h-24 text-center text-muted-foreground">
                <Loader2 class="inline size-4 animate-spin mr-2"/>
                Загрузка...
              </TableCell>
            </TableRow>
            <TableRow v-for="v in versions" v-else :key="v.id">
              <TableCell>
                <div class="flex items-center gap-2">
                  <span class="font-mono font-medium">v{{ v.version_number }}</span>
                  <Badge v-if="v.is_active" class="gap-1 text-xs">
                    <Star class="size-3"/>
                    активная
                  </Badge>
                </div>
              </TableCell>
              <TableCell class="text-sm">{{
                  (v as unknown as {
                    items_count?: number
                  }).items_count?.toLocaleString('ru-RU') ?? '—'
                }}
              </TableCell>
              <TableCell class="text-sm">{{
                  (v as unknown as { imports_count?: number }).imports_count ?? '—'
                }}
              </TableCell>
              <TableCell class="text-xs text-muted-foreground">{{ formatDateTime(v.created_at) }}</TableCell>
              <TableCell class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <Button variant="outline" size="sm" class="gap-1.5" as-child>
                    <Link :href="route('directories.version', [directoryId, v.id])">
                      <Pencil class="size-3.5"/>
                      Открыть
                    </Link>
                  </Button>
                  <Button
                      v-if="!v.is_active && canManage"
                      variant="outline"
                      size="sm"
                      class="gap-1.5"
                      @click="$emit('activate', v)"
                  >
                    <Star class="size-3.5"/>
                    Активировать
                  </Button>
                  <Button
                      v-if="!v.is_active && canDelete"
                      variant="outline"
                      size="icon"
                      class="size-8 text-muted-foreground hover:border-destructive hover:text-destructive"
                      @click="$emit('remove', v)"
                  >
                    <Trash2 class="size-3.5"/>
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="!loading && !versions.length">
              <TableCell colspan="5" class="h-24 text-center text-muted-foreground">Нет версий.</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </CardContent>
  </Card>
</template>
