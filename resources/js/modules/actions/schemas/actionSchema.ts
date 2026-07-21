import {z} from 'zod'

export const actionInputFieldSchema = z.object({
    key: z.string()
        .min(1, 'Key обязателен')
        .regex(/^[a-z][a-z0-9_]*$/, 'Только латиница, цифры и _, первый символ — буква'),
    label: z.string(),
    type: z.enum(['string', 'number', 'boolean', 'uuid', 'email']),
    required: z.boolean(),
    default: z.unknown(),
})

export const actionSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    slug: z.string()
        .min(1, 'Slug обязателен')
        .regex(/^[a-z][a-z0-9_-]*$/, 'Только латиница, цифры, _, -'),
    code: z.string(),
    description: z.string(),
    type: z.string().min(1, 'Тип обязателен'),
    is_active: z.boolean(),
    config: z.record(z.unknown()),
    input_fields: z.array(actionInputFieldSchema),
    default_backoff: z.array(z.number().int().min(0)),
    category_ids: z.array(z.string()),
})

export type ActionFormValues = z.infer<typeof actionSchema>
export type ActionInputFieldValues = z.infer<typeof actionInputFieldSchema>
