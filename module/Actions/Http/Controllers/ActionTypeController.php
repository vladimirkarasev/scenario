<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Actions\Enums\ActionType;
use Module\Actions\Services\ActionRegistry;

final class ActionTypeController extends Controller
{
    public function __construct(
        private readonly ActionRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        $items = [];

        foreach (ActionType::cases() as $type) {
            $items[] = [
                'value' => $type->value,
                'label' => $type->label(),
                'default_code' => $type->defaultCode(),
                'fields' => $this->fields($type),
            ];
        }

        return new JsonResponse(['items' => $items]);
    }

    /** @return list<array<string, mixed>> */
    private function fields(ActionType $type): array
    {
        $handler = $this->registry->handlerFor($type->value);
        $fields = [];

        foreach ($handler->configFields() as $field) {
            $fields[] = $field->toArray();
        }

        return $fields;
    }
}
