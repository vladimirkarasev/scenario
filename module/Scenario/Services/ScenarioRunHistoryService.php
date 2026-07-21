<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Illuminate\Database\Eloquent\Collection;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Models\ScenarioVersion;

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
        /** @var Collection<int, ScenarioRunStep> $steps */
        $steps = $run->stepHistory()
            ->whereIn('node_type', [
                ScenarioNodeType::Block->value,
                ScenarioNodeType::Condition->value,
            ])
            ->orderBy('id')
            ->get();

        if ($steps->isEmpty()) {
            return [];
        }

        $run->loadMissing('operator');
        $actor = $run->operator
            ? ($run->operator->name ?? $run->operator->login ?? null)
            : null;

        /** @var array<string, ScenarioVersion|null> $versionCache */
        $versionCache = [];
        /** @var array<string, array<string, mixed>> $lastInputByNode */
        $lastInputByNode = [];

        $events = [];

        foreach ($steps as $step) {
            $nodeTitle  = $this->nodeTitle($step, $versionCache);
            $cancelled  = $step->cancelled_at !== null;

            $events[] = [
                'at'         => $step->entered_at?->toIso8601String(),
                'actor'      => $actor,
                'type'       => 'transition',
                'node_type'  => $step->node_type->value,
                'node_title' => $nodeTitle,
                'cancelled'  => $cancelled,
            ];

            if (
                $step->node_type === ScenarioNodeType::Block
                && $step->exited_at !== null
            ) {
                /** @var array<string, mixed> $input */
                $input = is_array($step->input) ? $step->input : [];

                if (!empty($input)) {
                    $nodeId    = $step->node_id;
                    $prevInput = $lastInputByNode[$nodeId] ?? null;
                    $fieldLabels = $this->fieldLabels($step, $versionCache);

                    foreach ($input as $fieldId => $newValue) {
                        $oldValue = $prevInput[$fieldId] ?? null;

                        if ($prevInput !== null && $oldValue === $newValue) {
                            continue;
                        }

                        $events[] = [
                            'at'          => $step->exited_at->toIso8601String(),
                            'actor'       => $actor,
                            'type'        => ($prevInput === null || $oldValue === null) ? 'field_filled' : 'field_changed',
                            'node_type'   => $step->node_type->value,
                            'node_title'  => $nodeTitle,
                            'cancelled'   => $cancelled,
                            'field_label' => $fieldLabels[$fieldId] ?? $fieldId,
                            'field_id'    => (string) $fieldId,
                            'old_value'   => $prevInput !== null ? $oldValue : null,
                            'new_value'   => $newValue,
                        ];
                    }
                }

                $lastInputByNode[$step->node_id] = array_merge(
                    $lastInputByNode[$step->node_id] ?? [],
                    $input,
                );
            }
        }

        return $events;
    }

    /**
     * @param  array<string, ScenarioVersion|null>  $cache
     * @return array<string, string>
     */
    private function fieldLabels(ScenarioRunStep $step, array &$cache): array
    {
        $node = $this->findNode($step, $cache);
        if ($node === null) {
            return [];
        }

        $data   = is_array($node['data'] ?? null) ? $node['data'] : [];
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        $labels = [];
        foreach ($fields as $i => $field) {
            if (!is_array($field)) {
                continue;
            }
            $name          = is_string($field['name'] ?? null) ? $field['name'] : 'field_'.($i + 1);
            $labels[$name] = is_string($field['label'] ?? null) ? $field['label'] : $name;
        }

        return $labels;
    }

    /** @param  array<string, ScenarioVersion|null>  $cache */
    private function nodeTitle(ScenarioRunStep $step, array &$cache): ?string
    {
        $node = $this->findNode($step, $cache);
        if ($node === null) {
            return null;
        }

        $data  = is_array($node['data'] ?? null) ? $node['data'] : [];
        $title = $data['title'] ?? null;

        return is_string($title) ? $title : null;
    }

    /**
     * @param  array<string, ScenarioVersion|null>  $cache
     * @return array<string, mixed>|null
     */
    private function findNode(ScenarioRunStep $step, array &$cache): ?array
    {
        $versionId = $step->scenario_version_id;
        if ($versionId === null) {
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
            return $this->graphResolver->findNode($version, $step->node_id);
        } catch (\Throwable) {
            return null;
        }
    }
}
