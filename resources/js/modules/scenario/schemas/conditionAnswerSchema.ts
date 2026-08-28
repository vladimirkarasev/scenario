import {z} from 'zod'

export const conditionAnswerSchema = z.object({
    label: z.string().trim().min(1, 'Введите текст кнопки'),
    icon: z.string().nullable(),
    condition: z.string().trim().min(1, 'Условие должно возвращать true или false'),
    action: z.enum(['transition', 'url']),
    url: z.string(),
    width: z.enum(['full', 'half']),
    priority: z.number().int('Приоритет должен быть целым числом').min(1, 'Приоритет должен быть не меньше 1'),
})

export type ConditionAnswerFormValues = z.infer<typeof conditionAnswerSchema>
