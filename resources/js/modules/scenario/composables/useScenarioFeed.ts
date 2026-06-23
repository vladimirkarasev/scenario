import { useUrlSearchParams } from '@vueuse/core'
import { computed, reactive, ref, watch } from 'vue'
import type { Ref } from 'vue'
import {
  scenarioFeedRepository,
  type ScenarioFeedResponse,
  type ScenarioFeedRow,
} from '@/modules/scenario/repositories/scenarioFeedRepository'

const PAGE_SIZE = 20

type FeedParams = {
  'page[number]'?: string | string[]
  'filter[search]'?: string | string[]
  'filter[status]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
  if (!v) return []
  return Array.isArray(v) ? v : [v]
}

export type StatusTab = 'all' | 'active' | 'draft' | 'archived'

/**
 * activeFolder: 'all' | uuid — 'all' означает uncategorized (root).
 */
export function useScenarioFeed(
  activeFolder: Ref<string>,
  options: { excludeScenarioId?: Ref<string | null>; syncUrl?: boolean } = {},
) {
  const syncUrl = options.syncUrl ?? true
  const params: FeedParams = syncUrl
    ? useUrlSearchParams<FeedParams>('history', { removeNullishValues: true })
    : reactive<FeedParams>({})
  const loading = ref(false)
  const rows    = ref<ScenarioFeedRow[]>([])
  const meta    = ref<ScenarioFeedResponse['pagination']>({
    current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0, folders_total: 0, items_total: 0,
  })
  const countsByStatus = ref<ScenarioFeedResponse['counts_by_status']>({
    all: 0, active: 0, draft: 0, archived: 0,
  })

  const page = computed({
    get: () => Number(toArr(params['page[number]'])[0]) || 1,
    set: (v: number) => { params['page[number]'] = v > 1 ? String(v) : undefined },
  })

  const search = computed({
    get: () => toArr(params['filter[search]'])[0] ?? '',
    set: (v: string) => {
      params['filter[search]'] = v || undefined
      params['page[number]']   = undefined
    },
  })

  const statusTab = computed<StatusTab>({
    get: () => {
      const raw = toArr(params['filter[status]'])[0]
      return (raw === 'active' || raw === 'draft' || raw === 'archived') ? raw : 'all'
    },
    set: (v: StatusTab) => {
      params['filter[status]'] = v !== 'all' ? v : undefined
      params['page[number]']   = undefined
    },
  })

  async function load(): Promise<void> {
    loading.value = true
    try {
      const qs = new URLSearchParams()
      qs.set('page[number]', String(page.value))
      qs.set('page[size]', String(PAGE_SIZE))
      const searchTrim = search.value.trim()
      if (searchTrim) {
        // Глобальный поиск — parent_id не передаём
        qs.set('filter[search]', searchTrim)
      } else if (activeFolder.value === 'all') {
        // Корень: uncategorized сценарии + root папки
        qs.set('filter[parent_id]', 'null')
      } else {
        qs.set('filter[parent_id]', activeFolder.value)
      }
      if (statusTab.value !== 'all') qs.set('filter[status]', statusTab.value)
      const exclude = options.excludeScenarioId?.value
      if (exclude) qs.set('filter[exclude_scenario_id]', exclude)
      const res = await scenarioFeedRepository.fetch(qs)
      rows.value = res.data
      meta.value = res.pagination
      countsByStatus.value = res.counts_by_status
    } catch { /* silent */ }
    finally { loading.value = false }
  }

  const watchSources: Array<Ref<unknown>> = [page, search, statusTab, activeFolder]
  if (options.excludeScenarioId) watchSources.push(options.excludeScenarioId)
  watch(watchSources, () => { void load() }, { immediate: true })

  return { rows, meta, countsByStatus, loading, page, search, statusTab, load }
}
