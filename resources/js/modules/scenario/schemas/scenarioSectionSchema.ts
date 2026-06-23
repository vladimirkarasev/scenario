import {z} from 'zod'

export const scenarioSectionSchema = z.object({
    name: z.string().min(1, 'Название обязательно'),
    parent_id: z.string().nullable(),
    group_ids: z.array(z.string()),
    inherit_to_descendants: z.boolean(),
})

export type ScenarioSectionFormValues = z.infer<typeof scenarioSectionSchema>
