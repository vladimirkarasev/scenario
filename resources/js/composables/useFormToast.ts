import { toast } from 'vue-sonner'

export type EntityMessages = {
  created: string
  updated: string
  deleted?: string
}

export function useFormToast(messages: EntityMessages) {
  return {
    saved(isUpdate: boolean): void {
      toast.success(isUpdate ? messages.updated : messages.created)
    },
    deleted(): void {
      if (messages.deleted) toast.success(messages.deleted)
    },
    error(e: unknown, fallback = 'Ошибка'): void {
      toast.error(e instanceof Error ? e.message : fallback)
    },
  }
}
