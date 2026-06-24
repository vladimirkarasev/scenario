<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

final readonly class EmailSendResult
{
    public function __construct(
        public bool $sent,
        public string $transport,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(string $transport, ?string $messageId = null): self
    {
        return new self(sent: true, transport: $transport, messageId: $messageId);
    }

    public static function failed(string $transport, string $error): self
    {
        return new self(sent: false, transport: $transport, error: $error);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sent' => $this->sent,
            'transport' => $this->transport,
            'message_id' => $this->messageId,
            'error' => $this->error,
        ];
    }
}
