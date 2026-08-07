<?php

declare(strict_types=1);

namespace Tests\Unit\App\Providers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Module\Users\Models\User;
use Tests\TestCase;

final class ExpressionRateLimiterTest extends TestCase
{
    public function test_single_render_has_burst_and_sustained_limits_per_user(): void
    {
        $limits = $this->limits('expression-render');

        $this->assertSame([20, 600], array_map(static fn (Limit $limit): int => $limit->maxAttempts, $limits));
        $this->assertSame([1, 60], array_map(static fn (Limit $limit): int => $limit->decaySeconds, $limits));
        $this->assertStringContainsString('user:42', $limits[0]->key);
    }

    public function test_batch_render_has_separate_limits_per_user(): void
    {
        $limits = $this->limits('expression-render-batch');

        $this->assertSame([5, 60], array_map(static fn (Limit $limit): int => $limit->maxAttempts, $limits));
        $this->assertSame([1, 60], array_map(static fn (Limit $limit): int => $limit->decaySeconds, $limits));
        $this->assertStringContainsString('user:42', $limits[0]->key);
    }

    /** @return list<Limit> */
    private function limits(string $name): array
    {
        $user = new User;
        $user->setAttribute($user->getAuthIdentifierName(), 42);
        $request = Request::create('/api/expression/render', 'POST');
        $request->setUserResolver(static fn (): User => $user);

        $limiter = app(RateLimiter::class)->limiter($name);
        $this->assertNotNull($limiter);
        $limits = $limiter($request);
        $this->assertIsArray($limits);

        return array_values(array_filter($limits, static fn (mixed $limit): bool => $limit instanceof Limit));
    }
}
