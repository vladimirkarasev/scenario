<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\Handlers\DirectoryImportActionHandler;
use Module\Directories\Jobs\ImportDirectoryJob;
use Module\Directories\Models\Directory;
use Tests\TestCase;

final class DirectoryImportActionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_queues_directory_import_from_existing_file(): void
    {
        Bus::fake();

        $directory = Directory::query()->create([
            'name' => 'Leads',
            'slug' => 'leads',
            'source_type' => 'excel',
            'match_by' => 'email',
        ]);

        $action = new Action([
            'slug' => 'import-leads',
            'config' => [
                'directory_id' => $directory->id,
                'source_type' => 'file',
                'mode' => 'replace',
                'file_disk' => 'local',
                'file_path' => 'actions/leads/leads.xlsx',
                'mapping' => ['email' => 'email', 'name' => 'name'],
                'columns' => [
                    ['key' => 'email', 'name' => 'Email', 'type' => 'string'],
                    ['key' => 'name', 'name' => 'Name', 'type' => 'string'],
                ],
                'match_by' => 'email',
                'chunk_size' => 500,
            ],
        ]);

        $result = app(DirectoryImportActionHandler::class)->handle($action);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertSame($directory->id, $result->output['directory_id'] ?? null);
        $this->assertSame('file', $result->output['source_type'] ?? null);
        $this->assertSame('actions/leads/leads.xlsx', $result->output['file_path'] ?? null);

        Bus::assertDispatched(ImportDirectoryJob::class);
    }
}
