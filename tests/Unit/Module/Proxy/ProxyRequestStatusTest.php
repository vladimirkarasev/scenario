<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\Enums\ProxyRequestStatus;
use Tests\TestCase;

/**
 * Тестирует отображение статусов запроса: русские метки и цвета для UI.
 */
final class ProxyRequestStatusTest extends TestCase
{
    /**
     * Каждый статус должен возвращать читаемое русское название для интерфейса.
     */
    public function test_each_status_returns_correct_russian_label(): void
    {
        $this->assertSame('Получен',   ProxyRequestStatus::Received->label());
        $this->assertSame('Принят',    ProxyRequestStatus::Accepted->label());
        $this->assertSame('Отклонён',  ProxyRequestStatus::Rejected->label());
        $this->assertSame('Ошибка',    ProxyRequestStatus::Failed->label());
        $this->assertSame('Обработан', ProxyRequestStatus::Processed->label());
    }

    /**
     * Цвета используются в UI-компонентах — каждый статус имеет свой Tailwind-цвет.
     */
    public function test_each_status_returns_correct_tailwind_color(): void
    {
        $this->assertSame('slate',   ProxyRequestStatus::Received->color());
        $this->assertSame('blue',    ProxyRequestStatus::Accepted->color());
        $this->assertSame('amber',   ProxyRequestStatus::Rejected->color());
        $this->assertSame('red',     ProxyRequestStatus::Failed->color());
        $this->assertSame('emerald', ProxyRequestStatus::Processed->color());
    }

    /**
     * Все 5 значений enum должны быть представлены — защита от случайного удаления.
     */
    public function test_enum_covers_all_expected_values(): void
    {
        $values = array_column(ProxyRequestStatus::cases(), 'value');
        sort($values);

        $this->assertSame(
            ['accepted', 'failed', 'processed', 'received', 'rejected'],
            $values,
        );
    }
}
