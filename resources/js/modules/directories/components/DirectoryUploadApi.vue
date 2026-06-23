<script setup lang="ts">
import {Button} from '@/components/ui/button'
import {Card, CardContent} from '@/components/ui/card'
import {Globe, RefreshCw} from 'lucide-vue-next'

defineProps<{ proxyUuid: string | null }>()
const emit = defineEmits<{ openSync: [] }>()
</script>

<template>
  <Card class="border-border/60">
    <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
      <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10">
        <Globe class="size-7 text-primary"/>
      </div>
      <div class="space-y-1">
        <div class="font-semibold">Источник — Remote API</div>
        <div class="text-sm text-muted-foreground">
          Для этого справочника настроен Remote API.<br>
          Используйте синхронизацию вместо ручного импорта файлов.
        </div>
      </div>
      <Button :disabled="!proxyUuid" class="gap-2" @click="emit('openSync')">
        <RefreshCw class="size-4"/>
        Синхронизировать
      </Button>
      <p v-if="!proxyUuid" class="text-xs text-amber-400">
        Сначала выберите прокси в настройках
      </p>
    </CardContent>
  </Card>
</template>
