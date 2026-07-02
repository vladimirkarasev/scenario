import {z} from 'zod'

export const proxySectionSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    parent_id: z.string().nullable(),
})

export type ProxySectionFormValues = z.infer<typeof proxySectionSchema>
