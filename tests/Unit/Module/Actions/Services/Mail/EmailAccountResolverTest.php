<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Models\EmailAccount;
use Module\Actions\Services\Mail\EmailAccountResolver;
use Tests\TestCase;

final class EmailAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    private EmailAccountResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(EmailAccountResolver::class);
    }

    public function test_resolves_active_account_by_from_address(): void
    {
        $account = EmailAccount::query()->create([
            'from_address' => 'sales@acme.io',
            'from_name' => 'Acme Sales',
            'driver' => 'smtp',
            'is_active' => true,
        ]);

        $this->assertSame($account->id, $this->resolver->resolveByFrom('sales@acme.io')?->id);
    }

    public function test_returns_null_for_unknown_or_empty_from(): void
    {
        $this->assertNull($this->resolver->resolveByFrom('nobody@acme.io'));
        $this->assertNull($this->resolver->resolveByFrom(''));
        $this->assertNull($this->resolver->resolveByFrom(null));
    }

    public function test_ignores_inactive_account(): void
    {
        EmailAccount::query()->create([
            'from_address' => 'off@acme.io',
            'driver' => 'smtp',
            'is_active' => false,
        ]);

        $this->assertNull($this->resolver->resolveByFrom('off@acme.io'));
    }
}
