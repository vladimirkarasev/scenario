import { z } from 'zod'

export const actionRunSchema = z.object({
  input: z.record(z.unknown()),
})

export type ActionRunFormValues = z.infer<typeof actionRunSchema>
