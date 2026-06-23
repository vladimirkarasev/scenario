import {computed, onMounted, ref} from 'vue'
import {roleRepository} from '@/modules/roles/repositories/roleRepository'
import type {PermissionOption, Role} from '@/modules/roles/types/role'

export function useRoleList() {
    const search = ref('')
    const loading = ref(false)
    const roles = ref<Role[]>([])
    const availablePermissions = ref<PermissionOption[]>([])

    const filteredRoles = computed(() => {
        const q = search.value.toLowerCase()
        return q
            ? roles.value.filter(r => r.name.toLowerCase().includes(q) || (r.title ?? '').toLowerCase().includes(q))
            : roles.value
    })

    const permissionGroups = computed(() => {
        const groups: Record<string, PermissionOption[]> = {}
        for (const p of availablePermissions.value) {
            ;(groups[p.group] ??= []).push(p)
        }
        return groups
    })

    async function load(): Promise<void> {
        loading.value = true
        try {
            roles.value = await roleRepository.list(new URLSearchParams({'page[size]': '100'}))
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    async function loadPermissions(): Promise<void> {
        try {
            availablePermissions.value = await roleRepository.listPermissions()
        } catch { /* silent */
        }
    }

    onMounted(() => {
        load();
        loadPermissions()
    })

    return {search, loading, roles, availablePermissions, filteredRoles, permissionGroups, load}
}
