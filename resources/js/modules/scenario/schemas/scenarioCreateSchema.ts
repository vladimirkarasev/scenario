import {z} from 'zod'
import {SCENARIO_TYPE_VALUES} from '@/modules/scenario/lib/scenario-types'

export const scenarioCreateSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    type: z.enum(SCENARIO_TYPE_VALUES),
    description: z.string(),
    alias: z.string(),
    tags: z.string(),
    category_ids: z.array(z.string()),
})

export type ScenarioCreateFormValues = z.infer<typeof scenarioCreateSchema>
