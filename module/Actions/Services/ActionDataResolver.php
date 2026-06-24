<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use App\Services\Expression\ExpressionService;
use Module\Actions\Models\Action;

final readonly class ActionDataResolver
{
    public function __construct(
        private ExpressionService $expressionService,
    ) {}

    /**
     * Контекст для рендера шаблонов action: глобальный context + поля собственного scope
     * (input[action.code]) поднятые в корень. Так шаблоны видят `{{ client_uuid }}` (своё input-поле)
     * и `{{ other_action.result }}` (output другого action) одновременно.
     *
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function contextForAction(Action $action, array $context): array
    {
        $own = $context[$action->code] ?? null;

        if (! is_array($own)) {
            return $context;
        }

        $result = $context;

        foreach ($own as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Зарезолвить action.config с учётом scoped input этого action.
     *
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolveActionConfig(Action $action, array $context): array
    {
        $config = is_array($action->config) ? $action->config : [];

        return $this->resolveArray($config, $this->contextForAction($action, $context));
    }

    /** @param  array<string, mixed>  $input */
    public function resolve(mixed $value, array $input = []): mixed
    {
        return $this->expressionService->render($value, $input, emptyExpressionValueAsBlank: true);
    }

    /**
     * @param  array<string, mixed> $value
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function resolveArray(array $value, array $input = []): array
    {
        $resolved = $this->expressionService->render($value, $input, emptyExpressionValueAsBlank: true);

        if (! is_array($resolved)) {
            return [];
        }

        $result = [];

        foreach ($resolved as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }
}
