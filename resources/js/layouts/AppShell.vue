<script setup lang="ts">
import {computed} from 'vue'
import type {Component} from 'vue'
import {Link, usePage} from '@inertiajs/vue3'
import {Button} from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {User2} from 'lucide-vue-next'

interface AuthUserView {
  name?: string
  login?: string
  email?: string
  host?: string
}

interface NavigationItem {
  label: string
  href: string
  active: boolean
  icon?: Component
}

const props = withDefaults(defineProps<{
  title: string
  description?: string
  authUser?: AuthUserView | null
  navigationItems?: NavigationItem[]
  flush?: boolean
}>(), {
  description: '',
  authUser: null,
  navigationItems: () => [],
  flush: false,
})

const page = usePage()

function pageAuthUser(value: unknown): AuthUserView | null {
  if (typeof value !== 'object' || value === null || !('user' in value)) return null
  const user = value.user
  return typeof user === 'object' && user !== null ? user as AuthUserView : null
}

const authUser = computed(() => props.authUser ?? pageAuthUser(page.props.auth))
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-background text-foreground">
    <header class="flex h-14 shrink-0 items-center gap-4 border-b border-border bg-card px-5">
      <div class="flex min-w-0 flex-col justify-center">
        <span class="truncate text-sm font-semibold leading-tight">{{ title }}</span>
        <span v-if="description" class="truncate text-xs leading-tight text-muted-foreground">
                    {{ description }}
                </span>
      </div>

      <nav v-if="navigationItems.length" class="flex flex-1 items-center gap-1 overflow-x-auto">
        <Link
            v-for="item in navigationItems"
            :key="item.href"
            :href="item.href"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="item.active
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
        >
          <component :is="item.icon" v-if="item.icon" class="size-3.5 shrink-0" />
          {{ item.label }}
        </Link>
      </nav>
      <div v-else class="flex-1" />

      <div class="flex shrink-0 items-center gap-2">
        <DropdownMenu v-if="authUser">
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="gap-2">
              <User2 class="size-4" />
              <span class="max-w-[120px] truncate">
                                {{ authUser?.name ?? authUser?.login ?? 'Гость' }}
                            </span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel class="space-y-0.5">
              <div class="text-sm font-medium">
                {{ authUser?.name ?? authUser?.login ?? 'Гость' }}
              </div>
              <div class="text-xs font-normal text-muted-foreground">
                {{ authUser?.email ?? authUser?.host ?? 'Локальная сессия' }}
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
              <Link :href="route('workspace')">Рабочая область</Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>

    <main :class="flush ? 'flex flex-1 overflow-hidden' : 'flex-1 overflow-auto'">
      <slot/>
    </main>
  </div>
</template>
