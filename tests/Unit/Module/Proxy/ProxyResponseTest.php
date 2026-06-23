<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\DTO\ProxyResponse;
use Tests\TestCase;

/**
 * Тестирует ProxyResponse — фабричные методы и иммутабельное добавление заголовка X-Request-Id.
 */
final class ProxyResponseTest extends TestCase
{
    /**
     * accepted() создаёт ответ со статусом 202 и переданным телом.
     */
    public function test_accepted_returns_202_with_given_body(): void
    {
        $response = ProxyResponse::accepted(['lead_id' => 42]);

        $this->assertSame(202, $response->statusCode);
        $this->assertSame(42, $response->body['lead_id']);
    }

    /**
     * ok() создаёт ответ со статусом 200.
     */
    public function test_ok_returns_200(): void
    {
        $response = ProxyResponse::ok(['items' => []]);

        $this->assertSame(200, $response->statusCode);
    }

    /**
     * error() помещает сообщение в поле 'message' тела ответа с переданным кодом.
     */
    public function test_error_stores_message_in_body_with_given_status_code(): void
    {
        $response = ProxyResponse::error('Webhook validation failed', 422);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('Webhook validation failed', $response->body['message']);
    }

    /**
     * error() по умолчанию использует статус 400 когда код не передан.
     */
    public function test_error_defaults_to_400_status_code(): void
    {
        $response = ProxyResponse::error('Bad request');

        $this->assertSame(400, $response->statusCode);
    }

    /**
     * withRequestId() возвращает новый иммутабельный объект с X-Request-Id в заголовках.
     * Исходный объект не изменяется.
     */
    public function test_with_request_id_adds_header_and_returns_new_instance(): void
    {
        $original = ProxyResponse::accepted();
        $withId   = $original->withRequestId('test-uuid-123');

        // Заголовок добавлен в новый объект
        $this->assertSame('test-uuid-123', $withId->headers['X-Request-Id']);

        // Исходный объект не изменился
        $this->assertArrayNotHasKey('X-Request-Id', $original->headers);
    }

    /**
     * withRequestId() сохраняет statusCode и body из оригинального ответа.
     */
    public function test_with_request_id_preserves_status_and_body(): void
    {
        $original = ProxyResponse::ok(['key' => 'value']);
        $withId   = $original->withRequestId('abc-123');

        $this->assertSame(200, $withId->statusCode);
        $this->assertSame('value', $withId->body['key']);
    }

    /**
     * Повторный вызов withRequestId() обновляет X-Request-Id и сохраняет предыдущие заголовки.
     */
    public function test_with_request_id_merges_with_existing_headers(): void
    {
        $response = new ProxyResponse(200, [], ['X-Custom' => 'foo']);
        $withId   = $response->withRequestId('req-999');

        $this->assertSame('req-999', $withId->headers['X-Request-Id']);
        $this->assertSame('foo',     $withId->headers['X-Custom']);
    }
}
