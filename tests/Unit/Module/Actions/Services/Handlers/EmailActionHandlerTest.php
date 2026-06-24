<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Models\EmailAccount;
use Module\Actions\Services\Handlers\EmailActionHandler;
use Module\Actions\Services\Mail\ActionEmail;
use Tests\TestCase;

final class EmailActionHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EmailActionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(EmailActionHandler::class);
    }

    public function test_sends_email_and_returns_success(): void
    {
        Mail::fake();

        $action = $this->emailAction([
            'to' => 'client@example.com',
            'subject' => 'Привет',
            'body_html' => '<p>Тело</p>',
        ]);

        $result = $this->handler->handle($action);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertTrue($result->output['sent']);
        $this->assertSame('smtp', $result->output['transport']);
        $this->assertSame(['client@example.com'], $result->output['to']);

        Mail::assertSent(ActionEmail::class);
    }

    public function test_fails_without_recipients(): void
    {
        $action = $this->emailAction(['subject' => 'Hi', 'body_text' => 'X']);

        $result = $this->handler->handle($action);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
    }

    public function test_fails_without_body(): void
    {
        $action = $this->emailAction(['to' => 'a@b.com', 'subject' => 'Hi']);

        $result = $this->handler->handle($action);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
    }

    public function test_proxy_account_resolved_by_from_returns_failure(): void
    {
        EmailAccount::query()->create([
            'from_address' => 'sender@acme.io',
            'from_name' => 'Acme',
            'driver' => 'proxy',
            'is_active' => true,
        ]);

        $action = $this->emailAction([
            'to' => 'client@example.com',
            'from' => 'sender@acme.io',
            'subject' => 'Hi',
            'body_text' => 'Body',
        ]);

        $result = $this->handler->handle($action);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $this->assertSame('proxy', $result->output['transport']);
        $this->assertStringContainsString('не реализован', (string) $result->error);
    }

    /** @param  array<string, mixed>  $config */
    private function emailAction(array $config): Action
    {
        return Action::query()->create([
            'name' => 'Email',
            'slug' => 'email-'.uniqid(),
            'code' => 'send_email',
            'type' => 'email',
            'is_active' => true,
            'config' => $config,
        ]);
    }
}
