import { z } from 'zod'

const jsonValue = z.union([z.record(z.unknown()), z.array(z.unknown())])

const mockVariantSchema = z.object({
  name: z.string().nullable(),
  status: z.number().int().min(100, 'Статус 100..599').max(599, 'Статус 100..599'),
  body: jsonValue,
  headers: z.record(z.unknown()).nullable(),
  match: z.record(z.unknown()).nullable(),
})

export const webhookSchema = z.object({
  name: z.string().min(1, 'Название обязательно'),
  description: z.string(),
  is_active: z.boolean(),
  is_mocked: z.boolean(),
  config: jsonValue,
  mocks: z.array(mockVariantSchema),
})

export type WebhookFormValues = z.infer<typeof webhookSchema>
