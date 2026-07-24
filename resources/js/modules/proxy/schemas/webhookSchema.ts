import {z} from 'zod'

const jsonValue = z.union([z.record(z.unknown()), z.array(z.unknown())])

const mockVariantSchema = z.object({
    name: z.string().nullable(),
    status: z.number().int().min(100, 'Статус 100..599').max(599, 'Статус 100..599'),
    body: jsonValue,
    headers: z.record(z.unknown()).nullable(),
    is_active: z.boolean(),
})

/**
 * @param credentialTypeByHandler  handler_class → тип доступа, который он требует (null — доступ не нужен).
 *   Обработчик выбирается раньше доступа, поэтому connection_id обязателен только когда
 *   уже известно, что выбранный обработчик его требует.
 */
export function webhookSchema(credentialTypeByHandler: Record<string, string | null>) {
    return z.object({
        name: z.string().min(1, 'Название обязательно'),
        code: z.string().min(1, 'Code обязателен'),
        handler_class: z.string().min(1, 'Обработчик обязателен'),
        type: z.string().min(1, 'Тип обязателен'),
        description: z.string(),
        is_active: z.boolean(),
        is_mocked: z.boolean(),
        category_ids: z.array(z.string()),
        connection_id: z.number().nullable(),
        config: jsonValue,
        mocks: z.array(mockVariantSchema),
    }).superRefine((data, ctx) => {
        const requiredType = credentialTypeByHandler[data.handler_class] ?? null
        if (requiredType !== null && data.connection_id === null) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['connection_id'],
                message: 'Выберите доступ — обработчик требует авторизацию',
            })
        }
    })
}

export type WebhookFormValues = z.infer<ReturnType<typeof webhookSchema>>
