import axios from 'axios'
import {toast} from 'vue-sonner'
import {redirectToPartner} from '@/lib/auth-redirect'

const httpClient = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

httpClient.interceptors.request.use((config) => {
    const token = sessionStorage.getItem('access_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

// ── Token refresh ──────────────────────────────────────────────────────

let isRefreshing = false
let queue: Array<(token: string) => void> = []

// WeakSet tracks which requests have already been retried (avoids _retry property hacks)
const retried = new WeakSet<object>()

function drainQueue(token: string): void {
    queue.forEach(cb => cb(token))
    queue = []
}

httpClient.interceptors.response.use(
    response => response,
    async (error: unknown) => {
        if (!axios.isAxiosError(error)) return Promise.reject(error)

        const config = error.config
        if (error.response?.status === 403) {
            toast.error('Недостаточно прав для выполнения этого действия')
            return Promise.reject(error)
        }
        if (!config || error.response?.status !== 401 || retried.has(config)) {
            return Promise.reject(error)
        }

        const refreshToken = sessionStorage.getItem('refresh_token')
        if (!refreshToken) {
            sessionStorage.removeItem('access_token')
            redirectToPartner()
            return Promise.reject(error)
        }

        if (isRefreshing) {
            return new Promise((resolve) => {
                queue.push((token) => {
                    config.headers.Authorization = `Bearer ${token}`
                    resolve(httpClient(config))
                })
            })
        }

        retried.add(config)
        isRefreshing = true

        try {
            const {data} = await axios.post<{ access_token: string; refresh_token: string }>(
                '/api/embed/auth/refresh',
                {refresh_token: refreshToken},
            )

            sessionStorage.setItem('access_token', data.access_token)
            sessionStorage.setItem('refresh_token', data.refresh_token)

            config.headers.Authorization = `Bearer ${data.access_token}`
            drainQueue(data.access_token)

            return httpClient(config)
        } catch {
            sessionStorage.removeItem('access_token')
            sessionStorage.removeItem('refresh_token')
            queue = []
            redirectToPartner()
            return Promise.reject(error)
        } finally {
            isRefreshing = false
        }
    },
)

export default httpClient
