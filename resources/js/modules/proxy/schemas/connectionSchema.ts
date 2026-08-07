import {z} from 'zod'

export const connectionSchema = z.object({
    name: z.string().trim().min(1, 'Название обязательно'),
    credential_type: z.string().min(1, 'Тип доступа обязателен'),
    values: z.record(z.string(), z.unknown()),
})

export type ConnectionFormValues = z.infer<typeof connectionSchema>
