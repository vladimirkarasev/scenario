import {z} from 'zod'

export const actionScheduleSchema = z.object({
    enabled: z.boolean(),
    cron: z.string().min(1, 'Cron-выражение обязательно'),
    timezone: z.string().min(1, 'Таймзона обязательна'),
    input: z.record(z.unknown()),
    options: z.record(z.unknown()),
    settings: z.record(z.unknown()),
})

export type ActionScheduleFormValues = z.infer<typeof actionScheduleSchema>
