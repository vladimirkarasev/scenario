import { destroyJson, getJson, sendJson } from '@/lib/http'
import type { Project, ProjectsPage, ProjectPayload } from '@/modules/projects/types/project'

interface RawProject {
  id: string
  attributes: {
    name: string
    sitekey: string
    host: string
    shared_secret: string | null
    is_active: boolean
    created_at: string | null
  }
}

function normalizeProject(item: RawProject): Project {
  return {
    id: item.id,
    name: item.attributes.name,
    sitekey: item.attributes.sitekey,
    host: item.attributes.host,
    shared_secret: item.attributes.shared_secret,
    is_active: item.attributes.is_active,
    created_at: item.attributes.created_at,
  }
}

export const projectRepository = {
  async list(qs: URLSearchParams): Promise<ProjectsPage> {
    const raw = await getJson(`/api/projects?${qs}`, 'Не удалось загрузить проекты.') as {
      data: RawProject[]
      meta: ProjectsPage['meta']
    }
    return { data: raw.data.map(normalizeProject), meta: raw.meta }
  },

  async find(id: string): Promise<Project> {
    const raw = await getJson(`/api/projects/${id}`, 'Не удалось загрузить проект.') as { data: RawProject }
    return normalizeProject(raw.data)
  },

  async create(payload: ProjectPayload): Promise<Project> {
    const raw = await sendJson('/api/projects', { method: 'POST', body: payload, fallbackMessage: 'Не удалось создать проект.' }) as { data: RawProject }
    return normalizeProject(raw.data)
  },

  async update(id: string, payload: ProjectPayload): Promise<Project> {
    const raw = await sendJson(`/api/projects/${id}`, { method: 'PUT', body: payload, fallbackMessage: 'Не удалось сохранить проект.' }) as { data: RawProject }
    return normalizeProject(raw.data)
  },

  async remove(id: string): Promise<void> {
    await destroyJson(`/api/projects/${id}`, 'Не удалось удалить проект.')
  },
}
