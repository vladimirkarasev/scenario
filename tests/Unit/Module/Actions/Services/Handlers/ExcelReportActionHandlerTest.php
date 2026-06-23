<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Support\Facades\Storage;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\ExcelReportActionHandler;
use Tests\TestCase;

final class ExcelReportActionHandlerTest extends TestCase
{
    public function test_handle_creates_xlsx_report_from_rows(): void
    {
        Storage::fake('local');

        $action = new Action([
            'key' => 'lead-report',
            'config' => [
                'file_name' => 'leads',
                'sheet_name' => 'Leads',
                'columns' => [
                    ['header' => 'Email', 'key' => 'lead.email'],
                    ['header' => 'Name', 'key' => 'lead.name'],
                ],
            ],
        ]);

        $result = (new ExcelReportActionHandler(app(ActionDataResolver::class)))->handle($action, [
            'rows' => [
                ['lead' => ['email' => 'user@example.com', 'name' => 'User']],
            ],
        ]);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertSame('actions/lead-report/leads.xlsx', $result->output['path'] ?? null);
        $this->assertSame(1, $result->output['rows_count'] ?? null);
        $this->assertSame(2, $result->output['columns_count'] ?? null);
        Storage::disk('local')->assertExists('actions/lead-report/leads.xlsx');
    }
}
