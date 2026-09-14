<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

final readonly class ScenarioCallStack
{
    /** @param  list<ScenarioCallFrame>  $frames */
    private function __construct(private array $frames)
    {
    }

    public static function from(mixed $value): self
    {
        if (!is_array($value)) {
            return new self([]);
        }

        $frames = [];

        foreach ($value as $frame) {
            if (is_array($frame)) {
                $frames[] = ScenarioCallFrame::fromArray($frame);
            }
        }

        return new self($frames);
    }

    public function push(ScenarioCallFrame $frame): self
    {
        return new self([...$this->frames, $frame]);
    }

    public function pop(): self
    {
        return new self(array_slice($this->frames, 0, -1));
    }

    public function first(): ?ScenarioCallFrame
    {
        return $this->frames[0] ?? null;
    }

    public function last(): ?ScenarioCallFrame
    {
        if ($this->frames === []) {
            return null;
        }

        return $this->frames[count($this->frames) - 1];
    }

    /** @return list<array{version_id: string|null, revision_id: string|null, return_node_id: string|null}> */
    public function toArray(): array
    {
        return array_map(
            static fn(ScenarioCallFrame $frame): array => $frame->toArray(),
            $this->frames,
        );
    }
}
