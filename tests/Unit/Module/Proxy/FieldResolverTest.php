<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Services\FieldResolver;
use Tests\TestCase;

/**
 * Тестирует FieldResolver — маппинг значений из payload/query/headers/system в нормализованный массив.
 */
final class FieldResolverTest extends TestCase
{
    private FieldResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FieldResolver;
    }

    /**
     * Базовый кейс: поле без явного source берёт значение из payload по имени ключа.
     */
    public function test_resolve_reads_field_from_payload_by_default(): void
    {
        $fields = [ProxyField::make('phone')];
        $result = $this->resolver->resolve($fields, ['phone' => '+79990000000'], [], [], []);

        $this->assertSame('+79990000000', $result['phone']);
    }

    /**
     * source 'query.*' читает данные из строки запроса URL, а не из тела.
     */
    public function test_resolve_reads_field_from_query_params(): void
    {
        $fields = [ProxyField::make('source')->source('query.utm_source')];
        $result = $this->resolver->resolve($fields, [], ['utm_source' => 'landing'], [], []);

        $this->assertSame('landing', $result['source']);
    }

    /**
     * source 'headers.*' читает HTTP-заголовок; ключ всегда сравнивается в нижнем регистре.
     */
    public function test_resolve_reads_field_from_headers_case_insensitive(): void
    {
        $fields = [ProxyField::make('token')->source('headers.x-api-key')];
        // Заголовки приходят уже нормализованными в нижнем регистре после FieldResolver::value()
        $result = $this->resolver->resolve($fields, [], [], ['x-api-key' => 'secret-token'], []);

        $this->assertSame('secret-token', $result['token']);
    }

    /**
     * source 'system.*' читает системные мета-данные (ip, request_id, received_at).
     */
    public function test_resolve_reads_field_from_system_metadata(): void
    {
        $fields = [ProxyField::make('ip')->source('system.ip')];
        $result = $this->resolver->resolve($fields, [], [], [], ['ip' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $result['ip']);
    }

    /**
     * Dot-нотация поддерживает вложенные ключи в payload.
     */
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

    /**
     * Если значение в источнике отсутствует и задан default — подставляется default.
     */
    public function test_resolve_uses_default_when_source_value_is_absent(): void
    {
        $fields = [ProxyField::make('source')->source('query.source')->default('direct')];
        // query пуст — значение не придёт
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertSame('direct', $result['source']);
    }

    /**
     * Если значение отсутствует и default не задан — в результате null.
     */
    public function test_resolve_returns_null_when_field_is_absent_and_no_default(): void
    {
        $fields = [ProxyField::make('comment')];
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertNull($result['comment']);
    }

    /**
     * Неизвестный scope в source (не payload/query/headers/system/files) возвращает null.
     */
    public function test_resolve_returns_null_for_unknown_scope(): void
    {
        $fields = [ProxyField::make('x')->source('unknown.something')];
        $result = $this->resolver->resolve($fields, [], [], [], []);

        $this->assertNull($result['x']);
    }

    /**
     * value() с source 'headers.*' понижает регистр ключа перед поиском.
     */
    public function test_value_lowercases_header_key_before_lookup(): void
    {
        // Заголовки хранятся уже в нижнем регистре (нормализуются до передачи в FieldResolver)
        $result = $this->resolver->value('headers.X-Request-Id', [], [], ['x-request-id' => 'abc-123'], []);

        $this->assertSame('abc-123', $result);
    }

    /**
     * Несколько полей обрабатываются независимо — каждое берёт значение из своего источника.
     */
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
