import {computed, ref} from 'vue'
import type {Ref} from 'vue'
import type {Directory} from '@/modules/directories/types/directory'

export function useDirectoryFilters(directories: Ref<Directory[]>) {
    const sortBy = ref<'name' | 'updated'>('name')

    const filtered = computed(() => {
        if (sortBy.value === 'name') {
            return [...directories.value].sort((a, b) => a.name.localeCompare(b.name))
        }
        if (sortBy.value === 'updated') {
            return [...directories.value].sort((a, b) =>
                (b.latest_version?.created_at ?? '').localeCompare(a.latest_version?.created_at ?? ''),
            )
        }
        return directories.value
    })

    return {sortBy, filtered}
}
