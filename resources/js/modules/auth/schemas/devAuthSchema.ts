import {z} from 'zod'

export const devAuthSchema = z.object({
    projectId: z.string().min(1, 'Выберите проект'),
    userId: z.string().min(1, 'Выберите пользователя'),
})

export type DevAuthFormValues = z.infer<typeof devAuthSchema>
