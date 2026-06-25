import {z} from 'zod'

export const actionSectionSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    parent_id: z.string().nullable(),
})

export type ActionSectionFormValues = z.infer<typeof actionSectionSchema>
