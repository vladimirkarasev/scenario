<?php

declare(strict_types=1);

namespace Module\Actions\Events;

final readonly class EmailSent
{
    /** @param  list<string>  $to */
    public function __construct(
        public ?string $from,
        public array $to,
        public string $subject,
        public string $transport,
        public ?string $messageId,
    ) {
    }

    /** @return array<string, mixed> */
    public function logContext(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'transport' => $this->transport,
            'message_id' => $this->messageId,
        ];
    }
}
