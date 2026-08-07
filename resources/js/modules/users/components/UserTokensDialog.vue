<script setup lang="ts">
import {computed} from 'vue'
import {KeyRound, Trash2, X} from 'lucide-vue-next'
import CopyButton from '@/components/CopyButton.vue'
import {useAuthStore} from '@/stores/auth'
import {useUserTokens} from '@/modules/users/composables/useUserTokens'
import type {User} from '@/modules/users/types/user'

const auth = useAuthStore()
const canManageTokens = computed(() => auth.hasPermission('user_token_manage'))

const {
  user, showModal, tokens, loading, error,
  newTokenName, newTokenExpiresAt, creating, createError, createdToken,
  revokingId,
  open, close, create, revoke, dismissCreatedToken,
} = useUserTokens()

const tomorrow = new Date()
tomorrow.setDate(tomorrow.getDate() + 1)
const minTokenExpiration = [
  tomorrow.getFullYear(),
  String(tomorrow.getMonth() + 1).padStart(2, '0'),
  String(tomorrow.getDate()).padStart(2, '0'),
].join('-')

function formatDate(iso: string | null): string {
  return iso ? iso.slice(0, 10) : '—'
}

defineExpose<{open: (user: User) => Promise<void>}>({open})
</script>

<template>
  <Teleport to="body">
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="close"
    >
      <div
          class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]"
          style="max-height: 90vh">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div>
            <div class="text-[15px] font-bold text-slate-900">API-токены</div>
            <div v-if="user" class="mt-0.5 text-[12px] text-slate-400">{{ user.name }}</div>
          </div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="close">
            <X :size="18"/>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
          <div v-if="canManageTokens" class="mb-5 space-y-2">
            <label class="block text-[12px] font-semibold text-slate-700">Создать новый токен</label>
            <input
                v-model="newTokenName"
                type="text"
                class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100"
                placeholder="Название токена"
                @keydown.enter="create"
            />
            <div class="flex items-center gap-2">
              <input
                  v-model="newTokenExpiresAt"
                  type="date"
                  :min="minTokenExpiration"
                  class="h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] text-slate-700 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100 disabled:opacity-40"
                  :disabled="newTokenExpiresAt === 'never'"
              />
              <label class="flex cursor-pointer items-center gap-1.5 text-[12px] text-slate-500 select-none">
                <input
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded accent-blue-600"
                    :checked="newTokenExpiresAt === 'never'"
                    @change="newTokenExpiresAt = newTokenExpiresAt === 'never' ? '' : 'never'"
                />
                Бессрочный
              </label>
              <button
                  class="h-9 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                  :disabled="creating || !newTokenName.trim()"
                  @click="create"
              >
                {{ creating ? '…' : 'Создать' }}
              </button>
            </div>
            <div v-if="createError" class="rounded-lg bg-red-50 px-3 py-2 text-[12px] text-red-700">
              {{ createError }}
            </div>
          </div>

          <div v-if="createdToken" class="mb-5 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50">
            <div class="flex items-center justify-between border-b border-emerald-100 px-4 py-2.5">
              <div>
                <span class="text-[12px] font-semibold text-emerald-800">
                  Токен создан — скопируйте сейчас, он больше не будет показан
                </span>
                <div class="mt-0.5 text-[11px] text-emerald-600">
                  Создан {{ formatDate(createdToken.created_at) }}
                </div>
              </div>
              <button class="text-emerald-500 hover:text-emerald-700" @click="dismissCreatedToken">
                <X :size="14"/>
              </button>
            </div>
            <div class="flex items-center gap-2 px-4 py-3">
              <code class="min-w-0 flex-1 break-all font-mono text-[12px] text-emerald-900">
                {{ createdToken.plain_text_token }}
              </code>
              <CopyButton
                  :text="createdToken.plain_text_token"
                  :duration="2000"
                  class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-emerald-600 transition hover:bg-emerald-100"
                  title="Скопировать токен"
              />
            </div>
          </div>

          <div v-if="error" class="mb-4 rounded-lg bg-red-50 px-4 py-2.5 text-[13px] text-red-700">
            {{ error }}
          </div>
          <div v-if="loading" class="flex items-center justify-center py-8 text-[13px] text-slate-400">
            Загрузка…
          </div>
          <div v-else-if="!tokens.length" class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <KeyRound :size="28" class="text-slate-200"/>
            <p class="text-[13px] text-slate-400">Нет токенов</p>
          </div>
          <div v-else class="overflow-hidden rounded-xl border border-slate-200">
            <div
                v-for="(token, index) in tokens"
                :key="token.id"
                class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50"
                :class="index !== tokens.length - 1 ? 'border-b border-slate-100' : ''"
            >
              <KeyRound :size="14" class="flex-none text-slate-300"/>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-[13px] font-medium text-slate-800">{{ token.name }}</span>
                  <span
                      v-if="token.expires_at"
                      class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold"
                      :class="new Date(token.expires_at) < new Date()
                        ? 'bg-red-100 text-red-600'
                        : 'bg-amber-100 text-amber-700'"
                  >
                    до {{ formatDate(token.expires_at) }}
                  </span>
                  <span
                      v-else
                      class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"
                  >
                    бессрочный
                  </span>
                </div>
                <div class="mt-0.5 text-[11px] text-slate-400">
                  Создан {{ formatDate(token.created_at) }}
                  <template v-if="token.last_used_at">
                    · Использован {{ formatDate(token.last_used_at) }}
                  </template>
                </div>
              </div>
              <button
                  v-if="canManageTokens"
                  class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:opacity-40"
                  :disabled="revokingId === token.id"
                  @click="revoke(token.id)"
              >
                <Trash2 :size="13"/>
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 px-6 py-4">
          <button
              class="h-9 rounded-xl border border-slate-200 px-4 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
              @click="close"
          >
            Закрыть
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
