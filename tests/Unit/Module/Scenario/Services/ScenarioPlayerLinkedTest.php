<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioPlayerService;
use Tests\TestCase;

/**
 * Связные сценарии в плеере: переход (scenario_link) работает как подпрограмма —
 * по «Концу» связного сценария прогон возвращается в родителя и продолжается,
 * как один сквозной сценарий. Поддерживаются цепочки переходов (1→2→3).
 */
final class ScenarioPlayerLinkedTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioPlayerService $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = app(ScenarioPlayerService::class);
    }

    public function test_linked_scenario_returns_to_parent_and_shares_context(): void
    {
        // Дочерний: start → block(answer) → end
        [, $child] = $this->makeScenario(
            nodes: [
                $this->node('c_start', 'start'),
                $this->node('c_block', 'block', [
                    'title' => 'Вопрос',
                    'fields' => [$this->field('answer', 'number', 'answer')],
                ]),
                $this->node('c_end', 'end', ['title' => 'Конец дочернего']),
            ],
            edges: [$this->edge('c_start', 'c_block'), $this->edge('c_block', 'c_end')],
        );

        // Родитель: start → link(child) → end('{{ answer }}')
        [$parent, $parentVersion] = $this->makeScenario(
            nodes: [
                $this->node('p_start', 'start'),
                $this->node('p_link', 'scenario_link', [
                    'targetScenarioId' => $child->scenario_id,
                    'targetVersionId' => $child->id,
                ]),
                $this->node('p_end', 'end', ['title' => 'Итог: {{ answer }}']),
            ],
            edges: [$this->edge('p_start', 'p_link'), $this->edge('p_link', 'p_end')],
        );

        $run = $this->createRun($parent);

        // Прогон занырнул в дочерний сценарий и встал на его интерактивном блоке,
        // но идентичность осталась за родителем.
        $this->assertSame('c_block', $run->current_node_id);
        $this->assertSame($parent->id, $run->scenario_id);
        $this->assertSame($child->id, $run->scenario_version_id);

        $run = $this->player->continueRun($run, new ScenarioRunContinueData(['answer' => 42], null));

        // Дочерний дошёл до «Конца» → вернулись в родителя → его «Конец» завершил опрос.
        $this->assertSame('completed', $run->status->value);
        $this->assertSame('p_end', $run->current_node_id);
        $this->assertSame($parent->id, $run->scenario_id);
        $this->assertSame($parentVersion->id, $run->scenario_version_id);

        // Стек вызовов пуст, контекст общий — ответ из дочернего виден в «Конце» родителя.
        $this->assertSame([], $run->context['_call_stack'] ?? []);
        $this->assertSame('Итог: 42', $this->renderedTitle($run));
    }

    public function test_chain_of_links_runs_as_single_scenario(): void
    {
        // s3: start → block → end
        [$s3scenario, $s3] = $this->makeScenario(
            nodes: [
                $this->node('s3_start', 'start'),
                $this->node('s3_block', 'block', [
                    'title' => 'Шаг 3',
                    'fields' => [$this->field('done', 'text', 'done')],
                ]),
                $this->node('s3_end', 'end', ['title' => 'Финал цепочки']),
            ],
            edges: [$this->edge('s3_start', 's3_block'), $this->edge('s3_block', 's3_end')],
        );

        // s2: start → link(s3)   (хвостовой переход, без продолжения)
        [$s2scenario, $s2] = $this->makeScenario(
            nodes: [
                $this->node('s2_start', 'start'),
                $this->node('s2_link', 'scenario_link', [
                    'targetScenarioId' => $s3scenario->id,
                    'targetVersionId' => $s3->id,
                ]),
            ],
            edges: [$this->edge('s2_start', 's2_link')],
        );

        // s1: start → link(s2)   (хвостовой переход, без продолжения)
        [$s1, ] = $this->makeScenario(
            nodes: [
                $this->node('s1_start', 'start'),
                $this->node('s1_link', 'scenario_link', [
                    'targetScenarioId' => $s2scenario->id,
                    'targetVersionId' => $s2->id,
                ]),
            ],
            edges: [$this->edge('s1_start', 's1_link')],
        );

        $run = $this->createRun($s1);

        // Прошли 1→2→3 и встали на блоке третьего; прогон по-прежнему числится за s1.
        $this->assertSame('s3_block', $run->current_node_id);
        $this->assertSame($s1->id, $run->scenario_id);
        $this->assertCount(2, $run->context['_call_stack'] ?? []);

        $run = $this->player->continueRun($run, new ScenarioRunContinueData(['done' => 'ok'], null));

        // Хвостовая цепочка завершается на «Конце» третьего сценария.
        $this->assertSame('completed', $run->status->value);
        $this->assertSame('s3_end', $run->current_node_id);
        $this->assertSame($s1->id, $run->scenario_id);
        $this->assertSame('Финал цепочки', $this->renderedTitle($run));
    }

    public function test_jump_back_into_linked_scenario_restores_its_version(): void
    {
        [, $child] = $this->makeScenario(
            nodes: [
                $this->node('c_start', 'start'),
                $this->node('c_block', 'block', [
                    'title' => 'Вопрос',
                    'fields' => [$this->field('answer', 'number', 'answer')],
                ]),
                $this->node('c_end', 'end', ['title' => 'Конец дочернего']),
            ],
            edges: [$this->edge('c_start', 'c_block'), $this->edge('c_block', 'c_end')],
        );

        [$parent] = $this->makeScenario(
            nodes: [
                $this->node('p_start', 'start'),
                $this->node('p_link', 'scenario_link', [
                    'targetScenarioId' => $child->scenario_id,
                    'targetVersionId' => $child->id,
                ]),
                $this->node('p_end', 'end', ['title' => 'Итог']),
            ],
            edges: [$this->edge('p_start', 'p_link'), $this->edge('p_link', 'p_end')],
        );

        $run = $this->createRun($parent);
        // Прошли дочерний и вернулись в родителя — прогон завершён.
        $run = $this->player->continueRun($run, new ScenarioRunContinueData(['answer' => 42], null));
        $this->assertSame('completed', $run->status->value);

        // Откат на блок дочернего сценария не должен падать и восстанавливает его версию.
        $run = $this->player->jumpRun($run, new ScenarioRunJumpData('c_block'));

        $this->assertSame('active', $run->status->value);
        $this->assertSame('c_block', $run->current_node_id);
        $this->assertSame($child->id, $run->scenario_version_id);
        // Стек вызовов восстановлен — после повторного прохождения вернёмся в родителя.
        $this->assertCount(1, $run->context['_call_stack'] ?? []);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createRun(Scenario $scenario): ScenarioRun
    {
        return $this->player->createRun(new ScenarioRunData(
            scenarioId: $scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return array{0: Scenario, 1: ScenarioVersion}
     */
    private function makeScenario(array $nodes, array $edges): array
    {
        $scenario = Scenario::query()->create(['name' => 'Linked test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create([
            'scenario_id' => $scenario->id,
            'status' => 'active',
        ]);
        $this->createRevision($version, [
            'schema_json' => ['nodes' => $nodes, 'edges' => $edges],
            'nodes_json' => $nodes,
            'edges_json' => $edges,
        ]);
        $scenario->forceFill(['active_version_id' => $version->id])->save();

        return [$scenario, $version];
    }

    /** @return array<string, mixed> */
    private function node(string $id, string $type, array $data = []): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data];
    }

    /** @return array<string, string> */
    private function edge(string $source, string $target): array
    {
        return ['id' => $source.'-'.$target, 'source' => $source, 'target' => $target];
    }

    /** @return array<string, mixed> */
    private function field(string $name, string $type, string $varName): array
    {
        return [
            'id' => $name,
            'type' => $type,
            'name' => $name,
            'varName' => $varName,
            'label' => ucfirst($name),
            'required' => true,
            'value' => '',
        ];
    }

    private function renderedTitle(ScenarioRun $run): ?string
    {
        $payload = $this->player->payload($run);
        $rendered = $payload['run']['rendered'] ?? null;

        return is_array($rendered) && is_string($rendered['title'] ?? null)
            ? $rendered['title']
            : null;
    }
}
