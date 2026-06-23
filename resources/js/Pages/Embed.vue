<script setup lang="ts">
import {useAuthStore} from '@/stores/auth'
import {router} from '@inertiajs/vue3'
import axios from 'axios'
import {onMounted, ref} from 'vue'

const error = ref<string | null>(null)

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  const token = params.get('token')
  const redirect = params.get('redirect') ?? '/scenarios'

  if (!token) {
    error.value = 'Отсутствует токен авторизации.'
    return
  }

  try {
    const {data} = await axios.post('/api/embed/auth/exchange', {token})

    sessionStorage.setItem('access_token', data.access_token)
    sessionStorage.setItem('refresh_token', data.refresh_token)

    await useAuthStore().initialize()

    router.visit(redirect)
  } catch (e: unknown) {
    error.value = axios.isAxiosError(e)
        ? (e.response?.data?.message ?? 'Ошибка авторизации.')
        : 'Ошибка авторизации.'
  }
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50">
    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">
      {{ error }}
    </div>
    <div v-else class="flex flex-col items-center gap-3 text-slate-400">
      <svg class="h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
      <span class="text-sm">Авторизация…</span>
    </div>
  </div>
</template>
