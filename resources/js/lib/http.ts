import axios, {type AxiosRequestConfig, type Method} from 'axios'
import httpClient from '@/lib/http-client'

// Ошибка в формате JSON:API (новый единый конверт: errors[] + meta).
interface JsonApiError {
    status?: string
    code?: string
    title?: string
    detail?: string
    source?: { pointer?: string }
}

interface ErrorPayload {
    message?: string
    // Старый формат — Record<field, string[]>; новый (JSON:API) — массив объектов.
    errors?: Record<string, string[]> | JsonApiError[]
}

export class HttpValidationError extends Error {
    constructor(
        message: string,
        public readonly errors: Record<string, string[]>,
    ) {
        super(message)
        this.name = 'HttpValidationError'
    }
}

function isJsonApiErrors(errors: ErrorPayload['errors']): errors is JsonApiError[] {
    return Array.isArray(errors)
}

// `/data/attributes/group_ids.0` → `group_ids.0`; иначе — код ошибки или '_'.
function fieldFromError(error: JsonApiError): string {
    const match = (error.source?.pointer ?? '').match(/\/data\/attributes\/(.+)$/)
    return match ? match[1] : (error.code ?? '_')
}

// Свести JSON:API errors[] к привычному Record<field, string[]>.
function jsonApiFieldErrors(errors: JsonApiError[]): Record<string, string[]> {
    const result: Record<string, string[]> = {}
    for (const error of errors) {
        const field = fieldFromError(error)
        ;(result[field] ??= []).push(error.detail || error.title || '')
    }
    return result
}

interface SendJsonOptions {
    method?: Method
    body: unknown
    fallbackMessage: string
}

interface SendMultipartOptions {
    method?: Method
    body: FormData
    fallbackMessage: string
}

function resolveErrorMessage(payload: unknown, fallbackMessage: string): string {
    const p = payload as ErrorPayload | null

    if (isJsonApiErrors(p?.errors)) {
        const detail = p.errors.map(e => e.detail || e.title || '').filter(Boolean).join(' ')
        return detail || fallbackMessage
    }

    const validationMessage = Object.values(p?.errors ?? {}).flat().join(' ')

    return p?.message || validationMessage || fallbackMessage
}

function normalizeHttpError(error: unknown, fallbackMessage: string): never {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as ErrorPayload | null

        if (error.response?.status === 422 && data?.errors) {
            const fieldErrors = isJsonApiErrors(data.errors)
                ? jsonApiFieldErrors(data.errors)
                : data.errors

            throw new HttpValidationError(resolveErrorMessage(data, fallbackMessage), fieldErrors)
        }

        throw new Error(resolveErrorMessage(data, fallbackMessage))
    }

    throw error
}

async function requestJson<T = unknown>(
    url: string,
    fallbackMessage: string,
    config: AxiosRequestConfig = {},
): Promise<T> {
    try {
        const response = await httpClient.request<T>({
            url,
            ...config,
            headers: {
                Accept: 'application/json',
                ...config.headers,
            },
        })

        return response.data
    } catch (error) {
        normalizeHttpError(error, fallbackMessage)
    }
}

export async function getJson<T = unknown>(url: string, fallbackMessage: string): Promise<T> {
    return requestJson<T>(url, fallbackMessage, {
        method: 'GET',
    })
}

export async function sendJson<T = unknown>(url: string, {
    method = 'POST',
    body,
    fallbackMessage
}: SendJsonOptions): Promise<T> {
    return requestJson<T>(url, fallbackMessage, {
        method,
        data: body,
        headers: {
            'Content-Type': 'application/json',
        },
    })
}

export async function destroyJson(url: string, fallbackMessage: string): Promise<null> {
    await requestJson(url, fallbackMessage, {
        method: 'DELETE',
    })

    return null
}

export async function sendMultipart<T = unknown>(url: string, {
    method = 'POST',
    body,
    fallbackMessage
}: SendMultipartOptions): Promise<T> {
    return requestJson<T>(url, fallbackMessage, {
        method,
        data: body,
    })
}
