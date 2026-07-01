<script setup lang="ts">
import {Link} from '@inertiajs/vue3'
import {Layers, Shield, Users} from 'lucide-vue-next'
import {computed} from 'vue'
import {useAuthStore} from '@/stores/auth'

defineProps<{ active: 'users' | 'groups' | 'roles' }>()

const auth = useAuthStore()
const tabs = computed(() => [
  {key: 'users', label: 'Пользователи', icon: Users, href: '/users', permission: 'user_view'},
  {key: 'groups', label: 'Группы', icon: Layers, href: '/users/groups', permission: 'group_view'},
  {key: 'roles', label: 'Роли', icon: Shield, href: '/users/roles', permission: 'role_view'},
].filter(tab => auth.hasPermission(tab.permission)))
</script>

<template>
  <div
      class="flex items-center gap-0.5 rounded-xl border border-slate-200 bg-white p-1 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
    <Link
        v-for="t in tabs"
        :key="t.key"
        :href="t.href"
        class="flex h-8 items-center gap-2 rounded-lg px-3 text-[13px] font-medium transition-colors"
        :class="active === t.key ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'"
    >
      <component :is="t.icon" :size="13"/>
      {{ t.label }}
    </Link>
  </div>
</template>
