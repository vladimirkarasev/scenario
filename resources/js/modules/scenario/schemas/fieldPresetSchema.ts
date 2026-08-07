import {z} from 'zod'

export const fieldPresetSchema = z.object({
    name: z.string().trim().min(1, 'Название обязательно').max(120, 'Не более 120 символов'),
})

export type FieldPresetFormValues = z.infer<typeof fieldPresetSchema>
