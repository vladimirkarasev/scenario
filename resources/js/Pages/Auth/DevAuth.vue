<script setup lang="ts">
import {Badge} from '@/components/ui/badge'
import {FormActions, FormBody, FormError, FormSection, FormSelect} from '@/components/form'
import {Head} from '@inertiajs/vue3'
import {
  ArrowRight,
  BriefcaseBusiness,
  Code2,
  ShieldCheck,
  UserRound,
} from 'lucide-vue-next'
import {useDevAuth} from '@/modules/auth/composables/useDevAuth'
import type {DevAuthPageProps} from '@/modules/auth/types/devAuth'

const props = defineProps<DevAuthPageProps>()

const {
  formData,
  errors,
  formError,
  submitting,
  selectedProject,
  availableUsers,
  selectedUser,
  authorize,
  resetSelection,
} = useDevAuth(props)
</script>

<template>
  <Head title="Вход для разработки" />

  <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-muted/30 px-4 py-8 sm:px-6 lg:py-12">
    <div class="pointer-events-none absolute -left-28 top-12 size-80 rounded-full bg-primary/10 blur-3xl" />
    <div class="pointer-events-none absolute -right-24 bottom-0 size-96 rounded-full bg-blue-300/15 blur-3xl" />

    <section class="app-panel relative w-full max-w-lg">
      <div class="bg-card text-card-foreground">
        <header class="border-b border-border px-6 py-6 sm:px-8">
          <div class="mb-5 flex items-center gap-3">
            <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
              <Code2 :size="18" />
            </div>
            <div>
              <div class="text-sm font-semibold">Scenario</div>
              <div class="text-xs text-muted-foreground">Среда разработки</div>
            </div>
          </div>
          <h2 class="text-xl font-semibold tracking-tight">Вход в систему</h2>
          <p class="mt-1.5 text-sm leading-5 text-muted-foreground">
            Выберите контекст, от имени которого хотите продолжить.
          </p>
        </header>

        <form @submit.prevent="authorize">
          <FormBody :scrollable="false" padding="lg" spacing="lg">
            <FormSection
                title="Контекст авторизации"
                description="Доступны активные проекты и назначенные им пользователи."
            >
              <FormSelect
                  v-model="formData.projectId"
                  name="projectId"
                  label="Проект"
                  required
                  :error="errors.projectId"
              >
                <option value="" disabled>Выберите проект</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">
                  {{ project.name }}{{ project.host ? ` · ${project.host}` : '' }}
                </option>
              </FormSelect>

              <FormSelect
                  v-model="formData.userId"
                  name="userId"
                  label="Пользователь"
                  required
                  :disabled="selectedProject === null || availableUsers.length === 0"
                  :error="errors.userId"
                  :hint="selectedProject !== null && availableUsers.length === 0
                    ? 'В проекте нет доступных пользователей'
                    : undefined"
              >
                <option value="" disabled>Выберите пользователя</option>
                <option v-for="user in availableUsers" :key="user.id" :value="String(user.id)">
                  {{ user.name }}{{ user.login ? ` · ${user.login}` : '' }}
                </option>
              </FormSelect>
            </FormSection>

            <section
                v-if="selectedUser"
                class="rounded-xl border border-border bg-muted/40 p-4"
            >
              <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-background text-primary shadow-sm ring-1 ring-border">
                  <UserRound :size="18" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-foreground">{{ selectedUser.name }}</div>
                  <div class="mt-0.5 break-all text-xs text-muted-foreground">{{ selectedUser.email }}</div>
                  <div v-if="selectedUser.roles.length" class="mt-3 flex flex-wrap gap-1.5">
                    <Badge v-for="role in selectedUser.roles" :key="role" variant="secondary">
                      {{ role }}
                    </Badge>
                  </div>
                  <div v-else class="mt-2 text-xs text-muted-foreground">Роли не назначены</div>
                </div>
                <BriefcaseBusiness :size="16" class="mt-1 shrink-0 text-muted-foreground" />
              </div>
            </section>

            <FormError :message="formError" />
          </FormBody>

          <FormActions
              :submitting="submitting"
              :disabled="selectedUser === null"
              submit-label="Продолжить"
              cancel-label="Сбросить"
              @cancel="resetSelection"
          >
            <template #extra>
              <div class="mr-auto hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
                <ShieldCheck :size="14" />
                Временная dev-сессия
              </div>
              <ArrowRight :size="15" class="text-muted-foreground sm:hidden" />
            </template>
          </FormActions>
        </form>
      </div>
    </section>
  </main>
</template>
