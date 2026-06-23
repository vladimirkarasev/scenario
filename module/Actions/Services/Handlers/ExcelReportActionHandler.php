<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExcelReportActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private readonly ActionDataResolver $dataResolver,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $resolvedConfig = $this->dataResolver->resolve($action->config ?? [], $this->dataResolver->contextForAction($action, $input));
        $config = is_array($resolvedConfig) ? $this->stringKeyed($resolvedConfig) : [];
        $columns = $this->columns($config['columns'] ?? []);

        if ($columns === []) {
            return ActionResult::failed('Excel report action requires non-empty `columns` in config.');
        }

        $rows = $this->rows($config['rows'] ?? data_get($input, 'rows', [$input]));
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetName($this->stringValue($config['sheet_name'] ?? null, 'Report')));

        foreach ($columns as $index => $column) {
            $sheet->setCellValue([$index + 1, 1], $column['header']);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($columns as $columnIndex => $column) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], data_get($row, $column['key']));
            }
        }

        $fileName = $this->fileName($action, $config);
        $path = sprintf('actions/%s/%s', Str::slug($action->key), $fileName);
        $absolutePath = Storage::disk('local')->path($path);

        Storage::disk('local')->makeDirectory(dirname($path));

        (new Xlsx($spreadsheet))->save($absolutePath);
        $spreadsheet->disconnectWorksheets();

        return ActionResult::success([
            'format' => 'xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_name' => $fileName,
            'path' => $path,
            'rows_count' => count($rows),
            'columns_count' => count($columns),
        ]);
    }

    /**
     * @return array<int, array{header: string, key: string}>
     */
    private function columns(mixed $columns): array
    {
        if (! is_array($columns)) {
            return [];
        }

        $result = [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $header = $column['header'] ?? null;
            $key = $column['key'] ?? null;

            if (is_string($header) && $header !== '' && is_string($key) && $key !== '') {
                $result[] = ['header' => $header, 'key' => $key];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = $this->stringKeyed($row);
        }

        return $result;
    }

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function stringKeyed(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param array<string, mixed> $config */
    private function fileName(Action $action, array $config): string
    {
        $configured = $config['file_name'] ?? null;
        $fileName = is_string($configured) && $configured !== ''
            ? $configured
            : sprintf('%s-%s.xlsx', Str::slug($action->key), now()->format('Ymd-His'));

        return Str::endsWith(strtolower($fileName), '.xlsx') ? $fileName : "{$fileName}.xlsx";
    }

    private function sheetName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $name) ?? '');

        return mb_substr($name === '' ? 'Report' : $name, 0, 31);
    }
}
