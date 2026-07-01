import {computed, onBeforeUnmount, onMounted, ref} from 'vue'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import {roleRepository} from '@/modules/roles/repositories/roleRepository'
import type {UserListParams} from './useUserList'

export type FilterGroup = { id: string; name: string }
export type FilterRole = { id: string; name: string; title: string | null }

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

export function useUserFilters(params: UserListParams) {
    const groupNames = ref(new Map<string, string>())
    const roleData = ref(new Map<string, { name: string; title: string | null }>())

    const filterGroups = computed<FilterGroup[]>(() =>
        toArr(params['filter[group_ids][]']).map(id => ({id, name: groupNames.value.get(id) ?? id}))
    )
    const filterRoles = computed<FilterRole[]>(() =>
        toArr(params['filter[role_ids][]']).map(id => ({
            id,
            name: roleData.value.get(id)?.name ?? id,
            title: roleData.value.get(id)?.title ?? null,
        }))
    )
    const hasFilters = computed(() => filterGroups.value.length > 0 || filterRoles.value.length > 0)

    // Resolve display names for URL-restored filters
    onMounted(async () => {
        const groupIds = toArr(params['filter[group_ids][]'])
        if (groupIds.length) {
            const results = await Promise.all(
                groupIds.map(id => groupRepository.find(id).catch(() => null)),
            )
            results.forEach((g, i) => {
                if (g) groupNames.value.set(groupIds[i], g.name)
            })
        }
        const roleIds = toArr(params['filter[role_ids][]'])
        if (roleIds.length) {
            try {
                const allRoles = await roleRepository.list(new URLSearchParams({'page[size]': '100'}))
                allRoles.forEach(r => {
                    if (roleIds.includes(String(r.id)))
                        roleData.value.set(String(r.id), {name: r.name, title: r.title})
                })
            } catch { /* silent */
            }
        }
    })

    // ── Dropdown state ─────────────────────────────────────────────────

    const filterGroupSearch = ref('')
    const filterRoleSearch = ref('')
    const filterGroupOpen = ref(false)
    const filterRoleOpen = ref(false)
    const filterGroupResults = ref<FilterGroup[]>([])
    const filterRoleResults = ref<FilterRole[]>([])
    let groupTimer: ReturnType<typeof setTimeout> | null = null
    let roleTimer: ReturnType<typeof setTimeout> | null = null
    let groupRequestId = 0
    let roleRequestId = 0

    function onGroupInput(): void {
        if (groupTimer) clearTimeout(groupTimer)
        if (!filterGroupSearch.value.trim()) {
            filterGroupResults.value = [];
            return
        }
        groupTimer = setTimeout(async () => {
            const requestId = ++groupRequestId
            try {
                const qs = new URLSearchParams({'page[size]': '15', 'filter[search]': filterGroupSearch.value})
                const res = await groupRepository.list(qs)
                const active = toArr(params['filter[group_ids][]'])
                if (requestId === groupRequestId) {
                    filterGroupResults.value = res.data
                        .filter(g => !active.includes(g.id))
                        .map(g => ({id: g.id, name: g.name}))
                }
            } catch { /* silent */
            }
        }, 250)
    }

    function onRoleInput(): void {
        if (roleTimer) clearTimeout(roleTimer)
        if (!filterRoleSearch.value.trim()) {
            filterRoleResults.value = [];
            return
        }
        roleTimer = setTimeout(async () => {
            const requestId = ++roleRequestId
            try {
                const qs = new URLSearchParams({'page[size]': '20', 'filter[search]': filterRoleSearch.value})
                const res = await roleRepository.list(qs)
                const active = toArr(params['filter[role_ids][]'])
                if (requestId === roleRequestId) {
                    filterRoleResults.value = res
                        .filter(r => !active.includes(String(r.id)))
                        .map(r => ({id: String(r.id), name: r.name, title: r.title}))
                }
            } catch { /* silent */
            }
        }, 250)
    }

    function addGroup(g: FilterGroup): void {
        params['filter[group_ids][]'] = [...toArr(params['filter[group_ids][]']), g.id]
        groupNames.value.set(g.id, g.name)
        filterGroupSearch.value = ''
        filterGroupResults.value = []
        filterGroupOpen.value = false
    }

    function removeGroup(id: string): void {
        const ids = toArr(params['filter[group_ids][]']).filter(i => i !== id)
        params['filter[group_ids][]'] = ids.length ? ids : undefined
    }

    function addRole(r: FilterRole): void {
        params['filter[role_ids][]'] = [...toArr(params['filter[role_ids][]']), r.id]
        roleData.value.set(r.id, {name: r.name, title: r.title})
        filterRoleSearch.value = ''
        filterRoleResults.value = []
        filterRoleOpen.value = false
    }

    function removeRole(id: string): void {
        const ids = toArr(params['filter[role_ids][]']).filter(i => i !== id)
        params['filter[role_ids][]'] = ids.length ? ids : undefined
    }

    function clear(): void {
        params['filter[group_ids][]'] = undefined
        params['filter[role_ids][]'] = undefined
    }

    onBeforeUnmount(() => {
        groupRequestId++
        roleRequestId++
        if (groupTimer) clearTimeout(groupTimer)
        if (roleTimer) clearTimeout(roleTimer)
    })

    return {
        filterGroups, filterRoles, hasFilters,
        filterGroupSearch, filterRoleSearch,
        filterGroupOpen, filterRoleOpen,
        filterGroupResults, filterRoleResults,
        onGroupInput, onRoleInput,
        addGroup, removeGroup, addRole, removeRole, clear,
    }
}
