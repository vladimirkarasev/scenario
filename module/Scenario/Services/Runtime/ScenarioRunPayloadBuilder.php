<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Module\Scenario\DTO\ScenarioCallStack;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioVersionRevisionRepository;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerRegistry;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class ScenarioRunPayloadBuilder
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private NodeHandlerRegistry $nodeHandlers,
        private ScenarioVersionRevisionRepository $revisions,
        private VariableResolver $variableResolver,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
    ) {
    }

    /** @return array<string, mixed> */
    public function surveyData(ScenarioRun $run): array
    {
        $context = is_array($run->context) ? $run->context : [];
        $flat = $this->variableResolver->flatten($context);

        return array_filter(
            $flat,
            static fn(string $name): bool => !ScenarioContextKey::isSystem($name),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return array{run: array<string, mixed>} */
    public function build(ScenarioRun $run): array
    {
        $version = $this->runVersions->resolve($run);
        $renderContext = $this->renderContext($run);
        $currentNode = null;
        $rendered = null;

        if ($run->current_node_id !== null) {
            $currentNode = $this->graphResolver->findNode($version, $run->current_node_id);
            $rendered = $this->nodeHandlers->for($this->nodeData->type($currentNode))->render(
                $version,
                $currentNode,
                $renderContext,
            );
        }

        /** @var array<string, ScenarioVersion> $versionCache */
        $versionCache = [];
        [$rootVersionId, $rootVersion] = $this->rootVersion($run, $version, $versionCache);

        return ['run' => [
            'id' => $run->id,
            'number' => $run->number,
            'number_formatted' => $run->formattedNumber(),
            'scenario_id' => $run->scenario_id,
            'scenario_name' => $run->scenario?->name,
            'scenario_version_id' => $run->scenario_version_id,
            'scenario_version_revision_id' => $run->scenario_version_revision_id,
            'scenario_version_name' => $run->version?->name,
            'current_scenario_name' => $version->scenario?->name,
            'root_scenario_version_id' => $rootVersionId,
            'root_scenario_name' => $rootVersion->scenario?->name,
            'root_scenario_version_name' => $rootVersion->name,
            'scenario_version_created_at' => $run->version?->created_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
            'current_node_id' => $run->current_node_id,
            'status' => $run->status->value,
            'operator' => $this->operatorPayload($run),
            'client' => $this->clientPayload($run),
            'context' => $run->context ?? [],
            'current_node' => $currentNode,
            'rendered' => $rendered,
            'steps' => $run->steps->map(
                function (ScenarioRunStep $step) use ($version, $renderContext, &$versionCache): array {
                    return $this->stepPayload(
                        $step,
                        $version,
                        $renderContext,
                        $versionCache,
                    );
                },
            )->values()->all(),
        ]];
    }

    /** @return array<string, mixed> */
    private function renderContext(ScenarioRun $run): array
    {
        $operator = $run->operator;
        $call = is_array($run->context[ScenarioContextKey::Call->value] ?? null)
            ? $run->context[ScenarioContextKey::Call->value]
            : [];
        $isFinished = in_array($run->status, [ScenarioRunStatus::Completed, ScenarioRunStatus::Failed], true);

        return [
            ...($run->context ?? []),
            ScenarioContextKey::Run->value => [
                'id' => $run->id,
                'number' => $run->number,
                'number_formatted' => $run->formattedNumber(),
                'created_at' => $run->created_at?->format('d.m.Y H:i'),
                'completed_at' => $isFinished ? $run->updated_at?->format('d.m.Y H:i') : null,
            ],
            ScenarioContextKey::Operator->value => [
                'login' => $operator?->login,
                'name' => $operator?->name,
                'fio' => $operator?->fio,
            ],
            ScenarioContextKey::Project->value => [
                'name' => $operator?->project?->name,
                'id' => $operator?->project?->id,
            ],
            ScenarioContextKey::Call->value => [
                'incoming_phone' => $call['incoming_phone'] ?? null,
                'outgoing_phone' => $call['outgoing_phone'] ?? null,
                'internal_phone' => $call['internal_phone'] ?? null,
                'id' => $call['id'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, ScenarioVersion>  $cache
     * @return array{string|null, ScenarioVersion}
     */
    private function rootVersion(ScenarioRun $run, ScenarioVersion $fallback, array &$cache): array
    {
        $callStack = ScenarioCallStack::from($run->context[ScenarioContextKey::CallStack->value] ?? null);
        $rootFrame = $callStack->first();
        $versionId = $rootFrame !== null && $rootFrame->versionId !== null
            ? $rootFrame->versionId
            : $run->scenario_version_id;

        return [$versionId, $this->resolveVersionById($versionId, $fallback, $cache)];
    }

    /** @return array{id: int, name: string|null, fio: string|null, login: string|null}|null */
    private function operatorPayload(ScenarioRun $run): ?array
    {
        if ($run->operator === null) {
            return null;
        }

        return [
            'id' => $run->operator->id,
            'name' => $run->operator->name ?? $run->operator->login,
            'fio' => $run->operator->fio,
            'login' => $run->operator->login,
        ];
    }

    /** @return array{fio: string|null, phone: string|null}|null */
    private function clientPayload(ScenarioRun $run): ?array
    {
        $context = is_array($run->context) ? $run->context : [];
        $user = is_array($context['user'] ?? null) ? $context['user'] : [];
        $fio = is_string($user['fio'] ?? null) && $user['fio'] !== ''
            ? $user['fio']
            : (is_string($user['name'] ?? null) && $user['name'] !== '' ? $user['name'] : null);
        $phone = is_string($user['phone'] ?? null) && $user['phone'] !== '' ? $user['phone'] : null;

        return $fio === null && $phone === null ? null : ['fio' => $fio, 'phone' => $phone];
    }

    /**
     * @param  array<string, mixed>  $renderContext
     * @param  array<string, ScenarioVersion>  $versionCache
     * @return array<string, mixed>
     */
    private function stepPayload(
        ScenarioRunStep $step,
        ScenarioVersion $fallback,
        array $renderContext,
        array &$versionCache,
    ): array {
        $rendered = null;
        $version = $this->resolveVersionById($step->scenario_version_id, $fallback, $versionCache);

        try {
            $node = $this->graphResolver->findNode($version, $step->node_id);
            $rendered = $this->nodeHandlers->for($this->nodeData->type($node))->render($version, $node, $renderContext);
        } catch (\Throwable) {
        }

        return [
            'id' => $step->id,
            'node_id' => $step->node_id,
            'node_type' => $step->node_type->value,
            'scenario_version_id' => $step->scenario_version_id,
            'scenario_name' => $version->scenario?->name,
            'scenario_version_name' => $version->name,
            'input' => $step->input,
            'output' => $step->output,
            'rendered' => $rendered,
            'entered_at' => $step->entered_at?->toIso8601String(),
            'exited_at' => $step->exited_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, ScenarioVersion>  $cache */
    private function resolveVersionById(?string $versionId, ScenarioVersion $fallback, array &$cache): ScenarioVersion
    {
        if ($versionId === null || $versionId === $fallback->id) {
            return $fallback;
        }

        if (array_key_exists($versionId, $cache)) {
            return $cache[$versionId];
        }

        $version = ScenarioVersion::query()->find($versionId);

        if ($version !== null) {
            $version->setRelation('latestRevision', $this->revisions->getLastRevision($version));
        }

        return $cache[$versionId] = $version ?? $fallback;
    }
}
