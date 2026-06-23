<script setup lang="ts">
import {useAuthStore} from '@/stores/auth'
import {router} from '@inertiajs/vue3'
import axios from 'axios'
import {ref} from 'vue'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'

interface DemoDefaults {
  project_uuid: string
  shared_secret: string
  login: string
  name: string
  email: string
  roles: string
}

const props = defineProps<{ demo: DemoDefaults | null }>()

const projectUuid = ref(props.demo?.project_uuid ?? '')
const sharedSecret = ref(props.demo?.shared_secret ?? '')
const login = ref(props.demo?.login ?? '')
const name = ref(props.demo?.name ?? '')
const email = ref(props.demo?.email ?? '')
const roles = ref(props.demo?.roles ?? '')

const loading = ref(false)
const error = ref('')

async function authorize() {
  loading.value = true
  error.value = ''

  try {
    const {data} = await axios.post(
        '/api/dev/iframe-auth/authorize',
        {
          project_uuid: projectUuid.value,
          login: login.value,
          name: name.value,
          email: email.value || undefined,
          roles: roles.value.split(',').map((r) => r.trim()).filter(Boolean),
        },
        {headers: {Authorization: `Bearer ${sharedSecret.value}`}},
    )

    sessionStorage.setItem('access_token', data.access_token)
    sessionStorage.setItem('refresh_token', data.refresh_token)

    await useAuthStore().initialize()
    router.visit('/scenarios')
  } catch (e: unknown) {
    error.value = axios.isAxiosError(e)
        ? (e.response?.data?.message ?? 'Ошибка авторизации')
        : 'Ошибка авторизации'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50">
    <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-5">
        <h1 class="text-sm font-semibold text-slate-900">Dev: авторизация</h1>
        <p class="mt-0.5 text-xs text-slate-500">Симуляция embed-авторизации. Токен хранится в sessionStorage.</p>
      </div>

      <div class="space-y-4 px-6 py-5">
        <div class="space-y-1.5">
          <Label for="project-uuid">Project UUID</Label>
          <Input id="project-uuid" v-model="projectUuid" placeholder="018f1a2b-3c4d-7e8f-9a0b-…"/>
        </div>
        <div class="space-y-1.5">
          <Label for="shared-secret">Shared Secret</Label>
          <Input id="shared-secret" v-model="sharedSecret" placeholder="secret"/>
        </div>
        <div class="space-y-1.5">
          <Label for="login">Login</Label>
          <Input id="login" v-model="login" placeholder="user123"/>
        </div>
        <div class="space-y-1.5">
          <Label for="name">Name</Label>
          <Input id="name" v-model="name" placeholder="Иван Иванов"/>
        </div>
        <div class="space-y-1.5">
          <Label for="email">Email <span class="text-slate-400">(optional)</span></Label>
          <Input id="email" v-model="email" type="email" placeholder="user@example.com"/>
        </div>
        <div class="space-y-1.5">
          <Label for="roles">Roles <span class="text-slate-400">(через запятую)</span></Label>
          <Input id="roles" v-model="roles" placeholder="student, admin"/>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200">
          {{ error }}
        </p>

        <Button class="w-full" :disabled="loading" @click="authorize">
          {{ loading ? 'Авторизация…' : 'Войти' }}
        </Button>
      </div>
    </div>
  </div>
</template>
