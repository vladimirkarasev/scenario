<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Symfony\Component\Mime\Address;

final readonly class EmailMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        public ?string $from,
        public ?string $fromName,
        public array $to,
        public array $cc,
        public array $bcc,
        public string $subject,
        public string $bodyHtml,
        public string $bodyText,
    ) {
    }

    public function fromAddress(): ?Address
    {
        if ($this->from === null || $this->from === '') {
            return null;
        }

        return new Address($this->from, $this->fromName ?? '');
    }

    public function withFrom(?string $from, ?string $fromName): self
    {
        return new self(
            from: $from,
            fromName: $fromName,
            to: $this->to,
            cc: $this->cc,
            bcc: $this->bcc,
            subject: $this->subject,
            bodyHtml: $this->bodyHtml,
            bodyText: $this->bodyText,
        );
    }
}
