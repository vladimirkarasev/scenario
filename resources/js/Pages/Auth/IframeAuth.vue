<script setup lang="ts">
import axios from 'axios'
import {ref} from 'vue'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'

interface DemoDefaults {
  project_id: string
  name: string
  login: string
  email: string
  roles: string
}

const props = defineProps<{ demo: DemoDefaults | null }>()

const projectId = ref(props.demo?.project_id ?? '')
const name = ref(props.demo?.name ?? '')
const login = ref(props.demo?.login ?? '')
const email = ref(props.demo?.email ?? '')
const externalId = ref('')
const roles = ref(props.demo?.roles ?? '')

const loading = ref(false)
const error = ref('')

// Полностью повторяет прод: создаём пользователя проекта, получаем одноразовый _token
// и делаем полную перезагрузку на страницу с ?_token= — глобальный перехват в app.ts
// обменяет его на пару токенов.
async function launch() {
  loading.value = true
  error.value = ''

  try {
    const {data} = await axios.post('/api/dev/iframe-auth/launch', {
      project_id: projectId.value,
      name: name.value,
      login: login.value,
      email: email.value,
      external_id: externalId.value || undefined,
      roles: roles.value.split(',').map((r) => r.trim()).filter(Boolean),
    })

    window.location.href = `/scenarios?_token=${encodeURIComponent(data._token)}`
  } catch (e: unknown) {
    error.value = axios.isAxiosError(e)
        ? (e.response?.data?.message ?? 'Ошибка авторизации')
        : 'Ошибка авторизации'
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50">
    <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-5">
        <h1 class="text-sm font-semibold text-slate-900">Dev: симуляция embed-входа</h1>
        <p class="mt-0.5 text-xs text-slate-500">
          Создаёт пользователя проекта, выпускает одноразовый <code>_token</code> и делает редирект
          на страницу с <code>?_token=</code> — как настоящий iframe.
        </p>
      </div>

      <div class="space-y-4 px-6 py-5">
        <div class="space-y-1.5">
          <Label for="project-id">Project ID</Label>
          <Input id="project-id" v-model="projectId" placeholder="018f1a2b-3c4d-…" />
        </div>
        <div class="space-y-1.5">
          <Label for="name">Имя</Label>
          <Input id="name" v-model="name" placeholder="Иван Иванов" />
        </div>
        <div class="space-y-1.5">
          <Label for="login">Логин</Label>
          <Input id="login" v-model="login" placeholder="user123" />
        </div>
        <div class="space-y-1.5">
          <Label for="email">Email</Label>
          <Input id="email" v-model="email" type="email" placeholder="user@example.com" />
        </div>
        <div class="space-y-1.5">
          <Label for="external-id">External ID <span class="text-slate-400">(optional)</span></Label>
          <Input id="external-id" v-model="externalId" placeholder="crm-42" />
        </div>
        <div class="space-y-1.5">
          <Label for="roles">Роли <span class="text-slate-400">(через запятую)</span></Label>
          <Input id="roles" v-model="roles" placeholder="administrator" />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200">
          {{ error }}
        </p>

        <Button class="w-full" :disabled="loading" @click="launch">
          {{ loading ? 'Авторизация…' : 'Войти через _token' }}
        </Button>
      </div>
    </div>
  </div>
</template>
