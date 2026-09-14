<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Illuminate\Database\Eloquent\Collection;
use Module\Scenario\Enums\ScenarioRunHistoryEventType;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunHistoryEvent;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;

final readonly class ScenarioRunHistoryService
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildHistory(ScenarioRun $run): array
    {
        /** @var Collection<int, ScenarioRunHistoryEvent> $events */
        $events = ScenarioRunHistoryEvent::query()
            ->where('run_id', $run->id)
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        $run->loadMissing('operator');
        $actor = $run->operator
            ? ($run->operator->name ?? $run->operator->login ?? null)
            : null;

        /** @var array<string, ScenarioVersion|null> $versionCache */
        $versionCache = [];

        return array_values($events->map(
            fn (ScenarioRunHistoryEvent $event): array => $this->mapEvent($event, $actor, $versionCache)
        )->all());
    }

    /**
     * @param  array<string, ScenarioVersion|null>  $cache
     * @return array<string, mixed>
     */
    private function mapEvent(ScenarioRunHistoryEvent $event, ?string $actor, array &$cache): array
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        $base = [
            'at' => $event->occurred_at?->toIso8601String(),
            'actor' => $actor,
            'type' => $event->type->value,
            'node_id' => $event->node_id,
            'node_type' => $event->node_type?->value,
            'node_title' => $this->nodeTitle($event, $cache),
        ];

        return match ($event->type) {
            ScenarioRunHistoryEventType::FieldFilled, ScenarioRunHistoryEventType::FieldChanged => [
                ...$base,
                'field_label' => $payload['field_label'] ?? null,
                'field_id' => $payload['field_id'] ?? null,
                'old_value' => $payload['old_value'] ?? null,
                'new_value' => $payload['new_value'] ?? null,
            ],
            ScenarioRunHistoryEventType::ConditionEvaluated => [
                ...$base,
                'condition_mode' => $payload['mode'] ?? null,
                'condition_label' => $payload['label'] ?? null,
                'target_node_id' => $payload['target_node_id'] ?? null,
            ],
            ScenarioRunHistoryEventType::ActionCompleted, ScenarioRunHistoryEventType::ActionFailed => [
                ...$base,
                'action_id' => $payload['action_id'] ?? null,
                'action_name' => $payload['action_name'] ?? null,
                'code' => $payload['code'] ?? null,
                'output' => $payload['output'] ?? null,
                'error' => $payload['error'] ?? null,
            ],
            ScenarioRunHistoryEventType::ScenarioLinkFollowed => [
                ...$base,
                'target_scenario_id' => $payload['target_scenario_id'] ?? null,
                'target_scenario_name' => $payload['target_scenario_name'] ?? null,
            ],
            default => $base,
        };
    }

    /**
     * @param  array<string, ScenarioVersion|null>  $cache
     */
    private function nodeTitle(ScenarioRunHistoryEvent $event, array &$cache): ?string
    {
        $versionId = $event->scenario_version_id;
        $nodeId = $event->node_id;

        if ($versionId === null || $nodeId === null) {
            return null;
        }

        if (!array_key_exists($versionId, $cache)) {
            $cache[$versionId] = ScenarioVersion::query()->find($versionId);
        }

        $version = $cache[$versionId];

        if ($version === null) {
            return null;
        }

        try {
            $node = $this->graphResolver->findNode($version, $nodeId);
        } catch (\Throwable) {
            return null;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $title = $data['title'] ?? null;

        return is_string($title) ? $title : null;
    }
}
