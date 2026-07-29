import {computed, ref} from 'vue'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import {resolveBalloonTokens} from '@/modules/scenario/lib/balloon-tokens'
import {DEFAULT_MAP_CENTER, isValidMapPoint} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {DirectoryItem} from '@/modules/directories/types/directory'
import type {LatLng} from '@/modules/scenario/types/yandex-map'

interface MarkerDirectoryItem extends DirectoryItem {
    lat: number
    lng: number
}

interface SurveyDirectoryMapOptions {
    detailDocument: () => unknown
    directoryId: string
    latKey: () => string
    lngKey: () => string
    versionId: () => string
}

const ROW_WARNING_THRESHOLD = 1500

export function useSurveyDirectoryMap(options: SurveyDirectoryMapOptions) {
    const modalOpen = ref(false)
    const selectedItem = ref<MarkerDirectoryItem | null>(null)
    const {items, loading, loadItems} = useDirectoryItems(options.directoryId)

    const markerItems = computed<MarkerDirectoryItem[]>(() => {
        if (!options.latKey() || !options.lngKey()) return []

        return items.value.flatMap((item) => {
            const lat = Number(item.data[options.latKey()])
            const lng = Number(item.data[options.lngKey()])
            return isValidMapPoint(lat, lng) ? [{...item, lat, lng}] : []
        })
    })
    const tooManyRows = computed(() => markerItems.value.length > ROW_WARNING_THRESHOLD)
    const mapCenter = computed<LatLng>(() => {
        const item = selectedItem.value
        return item ? [item.lat, item.lng] : DEFAULT_MAP_CENTER
    })
    const resolvedDetailDocument = computed(() => {
        const item = selectedItem.value
        return item ? resolveBalloonTokens(options.detailDocument(), item.data) : null
    })

    function itemLabel(item: DirectoryItem): string {
        return String(Object.values(item.data)[0] ?? item.external_key ?? `Запись ${item.id}`)
    }

    function selectItem(item: MarkerDirectoryItem): void {
        selectedItem.value = item
    }

    async function setOpen(open: boolean): Promise<void> {
        modalOpen.value = open

        if (open && items.value.length === 0) {
            const versionId = options.versionId()
            await loadItems(versionId ? Number(versionId) : undefined)
        }
    }

    return {
        itemLabel,
        loading,
        mapCenter,
        markerItems,
        modalOpen,
        resolvedDetailDocument,
        selectedItem,
        selectItem,
        setOpen,
        tooManyRows,
    }
}
