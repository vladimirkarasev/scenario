<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionConfigField;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;

final readonly class TemplateFileActionHandler implements ActionHandlerInterface
{
    private const array FORMATS = ['xml', 'txt', 'html'];

    public function __construct(
        private ActionDataResolver $dataResolver,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $resolvedConfig = $this->dataResolver->resolve(
            $action->config ?? [],
            $this->dataResolver->contextForAction($action, $input)
        );
        $config = is_array($resolvedConfig) ? $this->stringKeyed($resolvedConfig) : [];
        $format = strtolower($this->stringValue($config['format'] ?? null, 'txt'));

        if (! in_array($format, self::FORMATS, true)) {
            return ActionResult::failed('Template action format must be one of: xml, txt, html.');
        }

        $template = $config['template'] ?? null;

        if (! is_string($template) || $template === '') {
            return ActionResult::failed('Template action requires `template` in config.');
        }

        $content = $this->content(
            $this->dataResolver->resolve($template, $this->dataResolver->contextForAction($action, $input))
        );
        $fileName = $this->fileName($action, $config, $format);
        $path = sprintf('actions/%s/%s', Str::slug($action->slug), $fileName);

        Storage::disk('local')->put($path, $content);

        return ActionResult::success([
            'format' => $format,
            'mime_type' => $this->mimeType($format),
            'file_name' => $fileName,
            'path' => $path,
        ]);
    }

    public function configFields(): iterable
    {
        yield ActionConfigField::make('format')
            ->label('Формат файла')
            ->type('select')
            ->required()
            ->default('txt')
            ->options([
                ['value' => 'txt', 'label' => 'TXT'],
                ['value' => 'html', 'label' => 'HTML'],
                ['value' => 'xml', 'label' => 'XML'],
            ]);

        yield ActionConfigField::make('file_name')
            ->label('Имя файла')
            ->type('string')
            ->placeholder('report-{{ scenario.id }}.txt');

        yield ActionConfigField::make('template')
            ->label('Шаблон')
            ->type('code')
            ->multiline()
            ->required()
            ->placeholder('Hello {{ scenario.name }}');
    }

    /** @param  array<string, mixed>  $config */
    private function fileName(Action $action, array $config, string $format): string
    {
        $configured = $config['file_name'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $this->ensureExtension($configured, $format);
        }

        return sprintf('%s-%s.%s', Str::slug($action->slug), now()->format('Ymd-His'), $format);
    }

    private function ensureExtension(string $fileName, string $format): string
    {
        return Str::endsWith(strtolower($fileName), ".{$format}")
            ? $fileName
            : "{$fileName}.{$format}";
    }

    private function mimeType(string $format): string
    {
        return match ($format) {
            'xml' => 'application/xml',
            'html' => 'text/html',
            default => 'text/plain',
        };
    }

    private function content(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_scalar($content) || $content === null) {
            return (string) $content;
        }

        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
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
}
