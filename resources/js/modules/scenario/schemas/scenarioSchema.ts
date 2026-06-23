import { z } from 'zod'

export const scenarioSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  description: z.string(),
  alias: z.string(),
  tags: z.string(),
  status: z.enum(['active', 'draft', 'archived']),
  active_version_id: z.string().nullable(),
  category_ids: z.array(z.string()),
  group_ids: z.array(z.string()),
})

export type ScenarioFormValues = z.infer<typeof scenarioSchema>

export const scenarioInputFieldSchema = z.object({
  key: z.string()
    .min(1, 'Ключ обязателен')
    .regex(/^[a-z][a-z0-9_]*$/, 'Только латиница, цифры и _, первый символ — буква'),
  label: z.string(),
  type: z.enum(['datetime', 'json', 'text', 'boolean']),
})

export type ScenarioInputFieldFormValues = z.infer<typeof scenarioInputFieldSchema>

export const scenarioVersionSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  status: z.enum(['active', 'draft', 'archived']),
  input_fields: z.array(scenarioInputFieldSchema),
})

export type ScenarioVersionFormValues = z.infer<typeof scenarioVersionSchema>
