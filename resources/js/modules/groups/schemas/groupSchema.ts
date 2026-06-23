import {z} from 'zod'

export const groupSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    slug: z.string()
        .min(1, 'Slug обязателен')
        .regex(/^[a-z0-9-]+$/, 'Только латиница, цифры и дефис'),
    description: z.string(),
    is_active: z.boolean(),
})

export type GroupFormValues = z.infer<typeof groupSchema>
