<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Question\QuestionNodeHandler;
use Tests\TestCase;

final class QuestionNodeHandlerTest extends TestCase
{
    public function test_question_reuses_interactive_block_rendering(): void
    {
        $handler = $this->app->make(QuestionNodeHandler::class);
        $node = [
            'id' => 'question_1',
            'type' => 'question',
            'data' => [
                'title' => 'Как вас зовут?',
                'hideTitle' => false,
                'fields' => [],
            ],
        ];

        $rendered = $handler->render(new ScenarioVersion(), $node, []);

        $this->assertTrue($handler->isInteractive($node));
        $this->assertSame('question', $rendered['type']);
        $this->assertSame('Как вас зовут?', $rendered['title']);
        $this->assertFalse($rendered['hideTitle']);
        $this->assertSame([], $rendered['blocks']);
    }
}
