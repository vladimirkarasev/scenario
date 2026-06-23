<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Module\Actions\Enums\ActionRunStatus;

final readonly class ActionResult
{
    /** @param  array<string, mixed>|null  $output */
    public function __construct(
        public ActionRunStatus $status,
        public ?array $output = null,
        public ?string $error = null,
    ) {
    }

    /** @param  array<string, mixed>|null  $output */
    public static function success(?array $output = null): self
    {
        return new self(
            status: ActionRunStatus::Success,
            output: $output,
        );
    }

    /** @param  array<string, mixed>|null  $output */
    public static function failed(string $error, ?array $output = null): self
    {
        return new self(
            status: ActionRunStatus::Failed,
            output: $output,
            error: $error,
        );
    }

    /** @param  array<string, mixed>|null  $output */
    public static function skipped(?string $reason = null, ?array $output = null): self
    {
        return new self(
            status: ActionRunStatus::Skipped,
            output: $output,
            error: $reason,
        );
    }
}
