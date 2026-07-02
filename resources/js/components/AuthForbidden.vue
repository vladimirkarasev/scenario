<script setup lang="ts">
import {Button} from '@/components/ui/button'

function reload(): void {
  try {
    (window.top ?? window).location.reload()
  } catch {
    window.location.reload()
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
    <div
        class="flex max-w-md flex-col items-center gap-4 rounded-xl border border-slate-200 bg-white px-8 py-10 text-center shadow-sm"
    >
      <div class="text-5xl font-bold text-slate-900">403</div>
      <h1 class="text-lg font-semibold text-slate-900">Доступ запрещён</h1>
      <p class="text-sm text-slate-500">
        Не удалось подтвердить авторизацию. Токен недействителен или срок его действия истёк.
      </p>
      <Button class="mt-2" @click="reload">Перезагрузить страницу</Button>
    </div>
  </div>
</template>
