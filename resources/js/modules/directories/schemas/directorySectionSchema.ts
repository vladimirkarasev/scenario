import {z} from 'zod'

export const directorySectionSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    parent_id: z.string().nullable(),
})

export type DirectorySectionFormValues = z.infer<typeof directorySectionSchema>
