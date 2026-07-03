import {z} from 'zod'
import {isSlug} from '@/lib/slug'

export const groupSchema = z.object({
    name: z.string().min(1, 'Название обязательно').max(255, 'Не более 255 символов'),
    slug: z.string()
        .min(1, 'Slug обязателен')
        .max(255, 'Не более 255 символов')
        .refine(isSlug, 'Используйте slug в формате latin-kebab-case'),
    ext_id: z.string().max(255, 'Не более 255 символов'),
    description: z.string(),
    is_active: z.boolean(),
})

export type GroupFormValues = z.infer<typeof groupSchema>
