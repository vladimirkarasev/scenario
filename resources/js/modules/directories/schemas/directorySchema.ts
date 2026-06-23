import { z } from 'zod'

export const directorySchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  slug: z.string()
    .min(1, 'Slug обязателен')
    .regex(/^[a-z0-9_-]+$/, 'Только латиница, цифры, _ и дефис'),
  description: z.string().nullable(),
  category_ids: z.array(z.string()),
  source_type: z.enum(['manual', 'excel', 'api', 'external']),
  match_by: z.string().nullable(),
  fields: z.array(z.unknown()),
})

export type DirectoryFormValues = z.infer<typeof directorySchema>
