<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use App\Exceptions\NotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyRequestIndexData;
use Module\Proxy\Enums\ProxyErrorCode;
use Module\Proxy\Models\ProxyRequest;

final readonly class ProxyRequestQueryService
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * @return LengthAwarePaginator<int, ProxyRequest>
     */
    public function paginate(ProxyRequestIndexData $filters): LengthAwarePaginator
    {
        $query = ProxyRequest::query()
            ->with('endpoint')
            ->whereHas('endpoint', fn ($query) => $query->where('project_id', $this->projectId()))
            ->forEndpoint($filters->endpointId)
            ->forStatus($filters->status)
            ->search($filters->search);
        $this->applySort($query, $filters->sort);

        return $query->paginate(
            $filters->pagination->size,
            ['*'],
            'page[number]',
            $filters->pagination->number,
        );
    }

    public function find(ProxyRequest $request): ProxyRequest
    {
        $belongsToProject = $request->endpoint()
            ->where('project_id', $this->projectId())
            ->exists();
        if (!$belongsToProject) {
            throw NotFoundException::from(ProxyErrorCode::RequestNotFound);
        }

        return $request->load('endpoint');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<ProxyRequest> $query
     * @param list<string> $sort
     */
    private function applySort(\Illuminate\Database\Eloquent\Builder $query, array $sort): void
    {
        $allowed = ['status', 'request_id', 'received_at', 'processed_at', 'created_at'];
        foreach ($sort as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) {
                $query->orderBy($column, $direction);
            }
        }
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Proxy request operations require a current project.');
    }
}
