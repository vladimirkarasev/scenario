<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Support\Facades\Storage;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\TemplateFileActionHandler;
use Tests\TestCase;

final class TemplateFileActionHandlerTest extends TestCase
{
    public function test_handle_creates_template_file_from_input(): void
    {
        Storage::fake('local');

        $action = new Action([
            'slug' => 'lead-template',
            'config' => [
                'format' => 'xml',
                'file_name' => 'lead',
                'template' => '<lead><email>{{ upper(lead.email) }}</email></lead>',
            ],
        ]);

        $result = (new TemplateFileActionHandler(app(ActionDataResolver::class)))->handle($action, [
            'lead' => ['email' => 'user@example.com'],
        ]);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertSame('actions/lead-template/lead.xml', $result->output['path'] ?? null);
        Storage::disk('local')->assertExists('actions/lead-template/lead.xml');
        $this->assertSame(
            '<lead><email>USER@EXAMPLE.COM</email></lead>',
            Storage::disk('local')->get('actions/lead-template/lead.xml'),
        );
    }
}
