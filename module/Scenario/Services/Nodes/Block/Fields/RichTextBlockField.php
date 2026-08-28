<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class RichTextBlockField implements BlockFieldInterface
{
    public function __construct(
        private string $id,
        private mixed $content,
    ) {
    }

    public function toArray(): array
    {
        return new RenderedBlockField($this->id, 'rich_text', $this->props())->toArray();
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        if (is_array($this->content) && ($this->content['type'] ?? null) === 'doc') {
            return ['document' => $this->content];
        }

        if (is_string($this->content)) {
            $decoded = json_decode($this->content, true);

            if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
                return ['document' => $decoded];
            }

            return ['html' => $this->content];
        }

        return ['document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]];
    }
}
