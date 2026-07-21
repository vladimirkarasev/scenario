import {sendJson} from '@/lib/http'

export const suggestRepository = {
    async suggest(uuid: string, query: string): Promise<Record<string, unknown>[]> {
        const raw = await sendJson(`/api/proxies/${uuid}`, {
            method: 'POST',
            body: {query},
            fallbackMessage: 'Не удалось загрузить подсказки.',
        })

        return normalizeItems(raw)
    },
}

function normalizeItems(raw: unknown): Record<string, unknown>[] {
    const list = Array.isArray(raw)
        ? raw
        : (raw && typeof raw === 'object' && Array.isArray((raw as {items?: unknown}).items)
            ? (raw as {items: unknown[]}).items
            : [])

    return list.filter((item): item is Record<string, unknown> =>
        item !== null && typeof item === 'object' && !Array.isArray(item),
    )
}
