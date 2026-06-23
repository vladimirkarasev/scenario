import axios, {type AxiosRequestConfig, type Method} from 'axios'
import httpClient from '@/lib/http-client'

interface ErrorPayload {
    message?: string
    errors?: Record<string, string[]>
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
    const validationMessage = Object.values(p?.errors ?? {}).flat().join(' ')

    return p?.message || validationMessage || fallbackMessage
}

function normalizeHttpError(error: unknown, fallbackMessage: string): never {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as ErrorPayload | null
        if (error.response?.status === 422 && data?.errors) {
            throw new HttpValidationError(
                data.message || resolveErrorMessage(data, fallbackMessage),
                data.errors,
            )
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
