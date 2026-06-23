<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const email = ref('admin@scenario.local')
const password = ref('password')

const loading = ref(false)
const error = ref('')

async function login() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.post('/api/auth/login', {
            email: email.value,
            password: password.value,
        })

        sessionStorage.setItem('access_token', data.token)

        await useAuthStore().initialize()
        router.visit('/scenarios')
    } catch (e: unknown) {
        error.value = axios.isAxiosError(e)
            ? (e.response?.data?.message ?? 'Неверный логин или пароль')
            : 'Ошибка авторизации'
    } finally {
        loading.value = false
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') login()
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h1 class="text-sm font-semibold text-slate-900">Вход</h1>
                <p class="mt-0.5 text-xs text-slate-500">Dev-режим. В проде авторизация через iframe.</p>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div class="space-y-1.5">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        placeholder="admin@example.com"
                        autocomplete="email"
                        @keydown="onKeydown"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="password">Пароль</Label>
                    <Input
                        id="password"
                        v-model="password"
                        type="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        @keydown="onKeydown"
                    />
                </div>

                <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200">
                    {{ error }}
                </p>

                <Button class="w-full" :disabled="loading" @click="login">
                    {{ loading ? 'Вход…' : 'Войти' }}
                </Button>

                <p class="text-center text-xs text-slate-400">
                    Нужна симуляция iframe-авторизации?
                    <a href="/auth" class="text-slate-600 underline underline-offset-2 hover:text-slate-900">Открыть</a>
                </p>
            </div>
        </div>
    </div>
</template>
