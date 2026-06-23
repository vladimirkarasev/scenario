import { getJson } from '@/lib/http'
import type { PaginationMeta } from '@/types/pagination'
import type {
  WebhookRequestLog,
  WebhookRequestLogDetail,
  WebhookRequestPage,
} from '@/modules/proxy/types/webhook'

interface RawRequestLog {
  id: string
  attributes: Omit<WebhookRequestLog, 'id'>
}

interface RawRequestLogDetail {
  id: string
  attributes: Omit<WebhookRequestLogDetail, 'id'>
}

function normalize(raw: RawRequestLog): WebhookRequestLog {
  return { id: raw.id, ...raw.attributes }
}

function normalizeDetail(raw: RawRequestLogDetail): WebhookRequestLogDetail {
  return { id: raw.id, ...raw.attributes }
}

export const webhookRequestRepository = {
  async list(qs: URLSearchParams): Promise<WebhookRequestPage> {
    const res = await getJson(`/api/proxy/requests?${qs}`, 'Не удалось загрузить запросы') as {
      data: RawRequestLog[]
      meta: PaginationMeta
    }
    return { data: res.data.map(normalize), meta: res.meta }
  },

  async get(id: string): Promise<WebhookRequestLogDetail> {
    const res = await getJson(`/api/proxy/requests/${id}`, 'Не удалось загрузить запрос') as { data: RawRequestLogDetail }
    return normalizeDetail(res.data)
  },
}
