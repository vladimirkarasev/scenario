import { z } from 'zod'

export const scenarioCreateSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  description: z.string(),
  alias: z.string(),
  tags: z.string(),
  category_ids: z.array(z.string()),
})

export type ScenarioCreateFormValues = z.infer<typeof scenarioCreateSchema>
