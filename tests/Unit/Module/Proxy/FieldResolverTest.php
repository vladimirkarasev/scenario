<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Services\FieldResolver;
use Tests\TestCase;

final class FieldResolverTest extends TestCase
{
    private FieldResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FieldResolver;
    }

    public function test_resolve_reads_field_from_payload_by_default(): void
    {
        $fields = [ProxyField::make('phone')];
        $result = $this->resolver->resolve($fields, ['phone' => '+79990000000'], [], [], []);

        $this->assertSame('+79990000000', $result['phone']);
    }

    public function test_resolve_reads_field_from_query_params(): void
    {
        $fields = [ProxyField::make('source')->source('query.utm_source')];
        $result = $this->resolver->resolve($fields, [], ['utm_source' => 'landing'], [], []);

        $this->assertSame('landing', $result['source']);
    }

    public function test_resolve_reads_field_from_headers_case_insensitive(): void
    {
        $fields = [ProxyField::make('token')->source('headers.x-api-key')];
        $result = $this->resolver->resolve($fields, [], [], ['x-api-key' => 'secret-token'], []);

        $this->assertSame('secret-token', $result['token']);
    }

    public function test_resolve_reads_field_from_system_metadata(): void
    {
        $fields = [ProxyField::make('ip')->source('system.ip')];
        $result = $this->resolver->resolve($fields, [], [], [], ['ip' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $result['ip']);
    }

    public function test_resolve_reads_nested_value_from_payload_using_dot_notation(): void
    {
        $fields = [ProxyField::make('city')->source('payload.address.city')];
        $result = $this->resolver->resolve(
            $fields,
            ['address' => ['city' => 'Москва']],
            [],
            [],
            [],
        );

        $this->assertSame('Москва', $result['city']);
    }

    public function test_resolve_uses_default_when_source_value_is_absent(): void
    {
        $fields = [ProxyField::make('source')->source('query.source')->default('direct')];
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertSame('direct', $result['source']);
    }

    public function test_resolve_returns_null_when_field_is_absent_and_no_default(): void
    {
        $fields = [ProxyField::make('comment')];
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertNull($result['comment']);
    }

    public function test_resolve_returns_null_for_unknown_scope(): void
    {
        $fields = [ProxyField::make('x')->source('unknown.something')];
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertNull($result['x']);
    }

    public function test_value_lowercases_header_key_before_lookup(): void
    {
        $result = $this->resolver->value('headers.X-Request-Id', [], [], ['x-request-id' => 'abc-123'], []);

        $this->assertSame('abc-123', $result);
    }

    public function test_resolve_handles_multiple_fields_with_different_sources(): void
    {
        $fields = [
            ProxyField::make('name')->source('payload.name'),
            ProxyField::make('source')->source('query.source'),
            ProxyField::make('ip')->source('system.ip'),
        ];

        $result = $this->resolver->resolve(
            $fields,
            ['name' => 'Иван'],
            ['source' => 'form'],
            [],
            ['ip' => '10.0.0.1'],
        );

        $this->assertSame('Иван', $result['name']);
        $this->assertSame('form', $result['source']);
        $this->assertSame('10.0.0.1', $result['ip']);
    }
}
