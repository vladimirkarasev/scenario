import { z } from 'zod'

export const roleSchema = z.object({
  name: z.string()
    .min(1, 'Системное имя обязательно')
    .regex(/^[a-z][a-z0-9_]*$/, 'Только латиница, цифры и _, первый символ — буква'),
  title: z.string(),
  description: z.string(),
  permissions: z.array(z.string()),
})

export type RoleFormValues = z.infer<typeof roleSchema>
