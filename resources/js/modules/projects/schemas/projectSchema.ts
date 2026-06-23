import {z} from 'zod'

export const projectSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    sitekey: z.string().min(1, 'Sitekey обязателен'),
    host: z.string().min(1, 'Host обязателен'),
    shared_secret: z.string().min(8, 'Минимум 8 символов'),
    is_active: z.boolean(),
})

export type ProjectFormValues = z.infer<typeof projectSchema>
