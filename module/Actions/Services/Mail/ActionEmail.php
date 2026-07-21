<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Module\Actions\DTO\EmailMessage;
use Symfony\Component\Mime\Email;

final class ActionEmail extends Mailable
{
    public function __construct(private readonly EmailMessage $message)
    {
        if ($this->message->bodyText !== '') {
            $text = $this->message->bodyText;
            $this->withSymfonyMessage(static function (Email $email) use ($text): void {
                $email->text($text);
            });
        }
    }

    public function envelope(): Envelope
    {
        $from = $this->message->from;

        return new Envelope(
            from: $from !== null && $from !== '' ? new Address($from, $this->message->fromName ?? '') : null,
            subject: $this->message->subject !== '' ? $this->message->subject : '(без темы)',
        );
    }

    public function content(): Content
    {
        $html = $this->message->bodyHtml !== ''
            ? $this->message->bodyHtml
            : ($this->message->bodyText !== '' ? nl2br(e($this->message->bodyText)) : '&nbsp;');

        return new Content(htmlString: $html);
    }
}
