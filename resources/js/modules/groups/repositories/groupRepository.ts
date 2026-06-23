import { destroyJson, getJson, sendJson } from '@/lib/http'
import type { Group, GroupMember, GroupsPage, GroupPayload } from '@/modules/groups/types/group'

interface RawGroup {
  id: string
  attributes: {
    name: string
    slug: string
    ext_id: string | null
    description: string | null
    is_active: boolean
    members_count: number
    created_at: string | null
  }
}

interface RawMember {
  id: string
  attributes: { name: string; email: string; login: string | null }
}

function normalizeGroup(item: RawGroup): Group {
  return {
    id: item.id,
    name: item.attributes.name,
    slug: item.attributes.slug,
    ext_id: item.attributes.ext_id,
    description: item.attributes.description,
    is_active: item.attributes.is_active,
    members_count: item.attributes.members_count,
    created_at: item.attributes.created_at,
  }
}

function normalizeMember(item: RawMember): GroupMember {
  return {
    id: item.id,
    name: item.attributes.name,
    email: item.attributes.email,
    login: item.attributes.login,
  }
}

export const groupRepository = {
  async list(qs: URLSearchParams): Promise<GroupsPage> {
    const raw = await getJson(`/api/groups?${qs}`, 'Не удалось загрузить группы.') as {
      data: RawGroup[]
      meta: GroupsPage['meta']
    }
    return { data: raw.data.map(normalizeGroup), meta: raw.meta }
  },

  async find(id: string): Promise<Group> {
    const raw = await getJson(`/api/groups/${id}`, 'Не удалось загрузить группу.') as { data: RawGroup }
    return normalizeGroup(raw.data)
  },

  async create(payload: GroupPayload): Promise<void> {
    await sendJson('/api/groups', { method: 'POST', body: payload, fallbackMessage: 'Не удалось создать группу.' })
  },

  async update(id: string, payload: GroupPayload): Promise<void> {
    await sendJson(`/api/groups/${id}`, { method: 'PUT', body: payload, fallbackMessage: 'Не удалось сохранить группу.' })
  },

  async remove(id: string): Promise<void> {
    await destroyJson(`/api/groups/${id}`, 'Не удалось удалить группу.')
  },

  async listMembers(groupId: string): Promise<GroupMember[]> {
    const raw = await getJson(`/api/groups/${groupId}/members`, 'Не удалось загрузить участников.') as { data: RawMember[] }
    return raw.data.map(normalizeMember)
  },

  async addMember(groupId: string, userId: number): Promise<void> {
    await sendJson(`/api/groups/${groupId}/members`, { method: 'POST', body: { user_id: userId }, fallbackMessage: 'Не удалось добавить участника.' })
  },

  async removeMember(groupId: string, userId: string): Promise<void> {
    await destroyJson(`/api/groups/${groupId}/members/${userId}`, 'Не удалось удалить участника.')
  },

  async searchUsers(search: string): Promise<GroupMember[]> {
    const raw = await getJson(`/api/users?search=${encodeURIComponent(search)}&per_page=10`, '') as { data: RawMember[] }
    return raw.data.map(normalizeMember)
  },
}
