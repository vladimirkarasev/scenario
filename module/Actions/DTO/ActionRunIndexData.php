<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Illuminate\Http\Request;

final readonly class ActionRunIndexData
{
    public function __construct(
        public ?string $status,
        public ?int $actionId,
        public int $limit,
    ) {}

    public static function fromRequest(Request $request, ?string $status = null): self
    {
        $filter = $request->array('filter');
        $pageSize = $request->input('page.size', 100);
        $actionId = $filter['action_id'] ?? null;

        return new self(
            status: $status ?? (isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== '' ? $filter['status'] : null),
            actionId: is_scalar($actionId) && (int) $actionId > 0 ? (int) $actionId : null,
            limit: max(1, min(500, is_scalar($pageSize) ? (int) $pageSize : 100)),
        );
    }
}
