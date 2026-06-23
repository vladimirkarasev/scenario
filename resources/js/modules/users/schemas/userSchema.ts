import { z } from 'zod'

export function userSchema(isEditing: boolean) {
  return z.object({
    name: z.string().min(1, 'Имя обязательно'),
    fio: z.string(),
    email: z.string().min(1, 'Email обязателен').email('Некорректный email'),
    login: z.string(),
    external_id: z.string(),
    password: isEditing
      ? z.string()
      : z.string().min(8, 'Минимум 8 символов'),
    roles: z.array(z.string()),
    group_ids: z.array(z.string()),
  })
}

export type UserFormValues = z.infer<ReturnType<typeof userSchema>>
