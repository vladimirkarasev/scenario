<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Module\Actions\DTO\EmailMessage;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;
use Module\Actions\Models\EmailAccount;
use Module\Actions\Services\Mail\EmailDispatcher;
use Tests\TestCase;

final class EmailDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_email_sent_event_on_success(): void
    {
        Mail::fake();
        Event::fake([EmailSent::class, EmailSendFailed::class]);

        $result = app(EmailDispatcher::class)->send($this->message(from: null));

        $this->assertTrue($result->sent);
        Event::assertDispatched(EmailSent::class, fn(EmailSent $e) => $e->to === ['client@example.com']);
        Event::assertNotDispatched(EmailSendFailed::class);
    }

    public function test_dispatches_failed_event_for_proxy_account(): void
    {
        Event::fake([EmailSent::class, EmailSendFailed::class]);

        EmailAccount::query()->create([
            'from_address' => 'sender@acme.io',
            'driver' => 'proxy',
            'is_active' => true,
        ]);

        $result = app(EmailDispatcher::class)->send($this->message(from: 'sender@acme.io'));

        $this->assertFalse($result->sent);
        Event::assertDispatched(EmailSendFailed::class, fn(EmailSendFailed $e) => $e->transport === 'proxy');
        Event::assertNotDispatched(EmailSent::class);
    }

    private function message(?string $from): EmailMessage
    {
        return new EmailMessage(
            from: $from,
            fromName: null,
            to: ['client@example.com'],
            cc: [],
            bcc: [],
            subject: 'Hi',
            bodyHtml: '<p>Body</p>',
            bodyText: '',
        );
    }
}
