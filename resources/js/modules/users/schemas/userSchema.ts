import {z} from 'zod'

export function userSchema(_isEditing: boolean) {
    return z.object({
        name: z.string().min(1, 'Имя обязательно'),
        fio: z.string(),
        email: z.string().min(1, 'Email обязателен').email('Некорректный email'),
        login: z.string().min(1, 'Логин обязателен'),
        external_id: z.string(),
        // Пароль необязателен (iframe-пользователи входят по токену); если задан — минимум 8 символов.
        password: z.string().refine(v => v === '' || v.length >= 8, 'Минимум 8 символов'),
        roles: z.array(z.string()),
        group_ids: z.array(z.string()),
    })
}

export type UserFormValues = z.infer<ReturnType<typeof userSchema>>
