import {destroyJson, getJson, sendJson, sendMultipart} from '@/lib/http'
import type {
    Directory,
    DirectoryImport,
    DirectoryItem,
    DirectoryListMeta,
    DirectoryPayload,
    DirectorySchemaField,
    DirectoryVersion,
    DirectoryVersionSyncOptions,
    DirectoryOtherOptionSettings,
    SourceType,
} from '@/modules/directories/types/directory'

type ApiResource<T> = { id: string; type: string; attributes: T }

function normalizeDirectory(item: ApiResource<Omit<Directory, 'id'>>): Directory {
    return {id: item.id, ...item.attributes}
}

function normalizeVersion(item: ApiResource<Omit<DirectoryVersion, 'id'>>): DirectoryVersion {
    return {id: Number(item.id), ...item.attributes}
}

function normalizeItem(item: ApiResource<Omit<DirectoryItem, 'id'>>): DirectoryItem {
    return {id: Number(item.id), ...item.attributes}
}

function normalizeImport(item: ApiResource<Omit<DirectoryImport, 'id'>>): DirectoryImport {
    return {id: Number(item.id), ...item.attributes}
}

export const directoryRepository = {
    async list(qs: URLSearchParams): Promise<{ items: Directory[]; meta: DirectoryListMeta }> {
        const res = await getJson<Record<string, unknown>>(`/api/directories?${qs}`, 'Не удалось загрузить справочники.')
        return {
            items: (res.data as ApiResource<Omit<Directory, 'id'>>[]).map(normalizeDirectory),
            meta: res.meta as DirectoryListMeta,
        }
    },

    async find(id: string): Promise<{ item: Directory }> {
        const res = await getJson<Record<string, unknown>>(`/api/directories/${id}`, 'Не удалось загрузить справочник.')
        return {item: normalizeDirectory(res.data as ApiResource<Omit<Directory, 'id'>>)}
    },

    async create(payload: DirectoryPayload): Promise<{ item: Directory }> {
        const res = await sendJson<Record<string, unknown>>('/api/directories', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать справочник.'
        })
        return {item: normalizeDirectory(res.data as ApiResource<Omit<Directory, 'id'>>)}
    },

    async update(id: string, payload: DirectoryPayload): Promise<{ item: Directory }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить справочник.'
        })
        return {item: normalizeDirectory(res.data as ApiResource<Omit<Directory, 'id'>>)}
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/directories/${id}`, 'Не удалось удалить справочник.')
    },

    async items(directoryId: string, qs: URLSearchParams): Promise<{ items: DirectoryItem[] }> {
        const res = await getJson<Record<string, unknown>>(`/api/directories/${directoryId}/items?${qs}`, 'Не удалось загрузить элементы.')
        return {items: (res.data as ApiResource<Omit<DirectoryItem, 'id'>>[]).map(normalizeItem)}
    },

    async createItem(directoryId: string, payload: Record<string, unknown>): Promise<{ item: DirectoryItem }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/items`, {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать элемент.'
        })
        return {item: normalizeItem(res.data as ApiResource<Omit<DirectoryItem, 'id'>>)}
    },

    async updateItem(directoryId: string, itemId: number, payload: Record<string, unknown>): Promise<{
        item: DirectoryItem
    }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/items/${itemId}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить элемент.'
        })
        return {item: normalizeItem(res.data as ApiResource<Omit<DirectoryItem, 'id'>>)}
    },

    async removeItem(directoryId: string, itemId: number): Promise<void> {
        await destroyJson(`/api/directories/${directoryId}/items/${itemId}`, 'Не удалось удалить элемент.')
    },

    async bulkRemoveItems(directoryId: string, ids: number[]): Promise<void> {
        await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/items`, {
            method: 'DELETE',
            body: {ids},
            fallbackMessage: 'Не удалось удалить элементы.'
        })
    },

    async versions(directoryId: string): Promise<{ items: DirectoryVersion[] }> {
        const res = await getJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions`, 'Не удалось загрузить версии.')
        return {items: (res.data as ApiResource<Omit<DirectoryVersion, 'id'>>[]).map(normalizeVersion)}
    },

    async createVersion(directoryId: string, payload: Record<string, unknown>): Promise<{ item: DirectoryVersion }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions`, {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать версию.'
        })
        return {item: normalizeVersion(res.data as ApiResource<Omit<DirectoryVersion, 'id'>>)}
    },

    async updateVersionCode(directoryId: string, versionId: number, code: string | null): Promise<{
        item: DirectoryVersion
    }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions/${versionId}/code`, {
            method: 'PATCH',
            body: {code},
            fallbackMessage: 'Не удалось сохранить код версии.'
        })
        return {item: normalizeVersion(res.data as ApiResource<Omit<DirectoryVersion, 'id'>>)}
    },

    async updateVersionSettings(directoryId: string, versionId: number, sourceType: SourceType, syncOptions?: DirectoryVersionSyncOptions, otherSettings?: DirectoryOtherOptionSettings): Promise<{
        item: DirectoryVersion
    }> {
        const body: Record<string, unknown> = {source_type: sourceType}
        if (syncOptions !== undefined) body.sync_options = syncOptions
        if (otherSettings !== undefined) {
            body.allow_other = otherSettings.allow_other
            body.other_label = otherSettings.other_label
            if (otherSettings.other_external_key !== undefined) body.other_external_key = otherSettings.other_external_key
        }
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions/${versionId}/settings`, {
            method: 'PATCH',
            body,
            fallbackMessage: 'Не удалось сохранить настройки версии.'
        })
        return {item: normalizeVersion(res.data as ApiResource<Omit<DirectoryVersion, 'id'>>)}
    },

    async updateVersionSchema(directoryId: string, versionId: number, fields: DirectorySchemaField[], matchBy: string | null, defaultSort?: string | null): Promise<{
        item: DirectoryVersion
    }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions/${versionId}/schema`, {
            method: 'PUT',
            body: {schema: fields, match_by: matchBy, default_sort: defaultSort ?? null},
            fallbackMessage: 'Не удалось сохранить схему.'
        })
        return {item: normalizeVersion(res.data as ApiResource<Omit<DirectoryVersion, 'id'>>)}
    },

    async activateVersion(directoryId: string, versionId: number): Promise<{ item: DirectoryVersion }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/versions/${versionId}/activate`, {
            method: 'POST',
            body: {},
            fallbackMessage: 'Не удалось активировать версию.'
        })
        return {item: normalizeVersion(res.data as ApiResource<Omit<DirectoryVersion, 'id'>>)}
    },

    async removeVersion(directoryId: string, versionId: number): Promise<void> {
        await destroyJson(`/api/directories/${directoryId}/versions/${versionId}`, 'Не удалось удалить версию.')
    },

    async imports(directoryId: string, versionId?: number): Promise<{ items: DirectoryImport[] }> {
        const qs = versionId != null ? `?version_id=${versionId}` : ''
        const res = await getJson<Record<string, unknown>>(`/api/directories/${directoryId}/imports${qs}`, 'Не удалось загрузить историю импортов.')
        return {items: (res.data as ApiResource<Omit<DirectoryImport, 'id'>>[]).map(normalizeImport)}
    },

    async importExcel(directoryId: string, body: FormData): Promise<{ item: DirectoryImport }> {
        const res = await sendMultipart<Record<string, unknown>>(`/api/directories/${directoryId}/imports`, {
            method: 'POST',
            body,
            fallbackMessage: 'Не удалось запустить импорт.'
        })
        return {item: normalizeImport(res.data as ApiResource<Omit<DirectoryImport, 'id'>>)}
    },

    async syncApi(directoryId: string, options?: DirectoryVersionSyncOptions): Promise<{ item: DirectoryImport }> {
        const res = await sendJson<Record<string, unknown>>(`/api/directories/${directoryId}/sync-api`, {
            method: 'POST',
            body: options ?? {},
            fallbackMessage: 'Не удалось запустить синхронизацию.'
        })
        return {item: normalizeImport(res.data as ApiResource<Omit<DirectoryImport, 'id'>>)}
    },
}
