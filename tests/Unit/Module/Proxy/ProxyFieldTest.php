<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\DTO\ProxyField;
use Tests\TestCase;

/**
 * Тестирует builder API ProxyField: цепочки вызовов, sourcePath, правила и флаги.
 */
final class ProxyFieldTest extends TestCase
{
    /**
     * Если source не задан явно — путь берётся как "payload.{key}".
     */
    public function test_source_path_defaults_to_payload_prefix_with_key(): void
    {
        $field = ProxyField::make('phone');

        $this->assertSame('payload.phone', $field->sourcePath());
    }

    /**
     * Явный source полностью заменяет автоматически сгенерированный путь.
     */
    public function test_explicit_source_overrides_default_path(): void
    {
        $field = ProxyField::make('source')->source('query.utm_source');

        $this->assertSame('query.utm_source', $field->sourcePath());
    }

    /**
     * required() делает поле обязательным и снимает nullable.
     */
    public function test_required_marks_field_as_required_and_clears_nullable(): void
    {
        $field = ProxyField::make('phone')->nullable()->required();

        $this->assertTrue($field->isRequired());
        $this->assertFalse($field->isNullable());
    }

    /**
     * nullable() снимает флаг required — поля не могут быть одновременно required и nullable.
     */
    public function test_nullable_clears_required_flag(): void
    {
        $field = ProxyField::make('comment')->required()->nullable();

        $this->assertFalse($field->isRequired());
        $this->assertTrue($field->isNullable());
    }

    /**
     * По умолчанию поле не filterable; после вызова filterable() — становится.
     */
    public function test_filterable_flag_is_false_by_default_and_true_after_setting(): void
    {
        $plain = ProxyField::make('status');
        $filterable = ProxyField::make('status')->filterable();

        $this->assertFalse($plain->isFilterable());
        $this->assertTrue($filterable->isFilterable());
    }

    /**
     * filterKey без явного аргумента совпадает с key поля.
     * Если передан аргумент — используется он.
     */
    public function test_filter_key_defaults_to_field_key_and_can_be_overridden(): void
    {
        $defaultKey = ProxyField::make('city_id')->filterable();
        $customKey = ProxyField::make('city_id')->filterable('filter_city');

        $this->assertSame('city_id', $defaultKey->filterKey());
        $this->assertSame('filter_city', $customKey->filterKey());
    }

    /**
     * default() задаёт запасное значение, которое FieldResolver использует при отсутствии данных.
     */
    public function test_default_value_is_returned_when_set(): void
    {
        $field = ProxyField::make('source')->default('organic');

        $this->assertSame('organic', $field->defaultValue());
    }

    /**
     * rules() принимает массив или строку с разделителем '|' — оба варианта сохраняются одинаково.
     */
    public function test_rules_accept_array_and_pipe_string_format(): void
    {
        $withArray = ProxyField::make('email')->rules(['nullable', 'email', 'max:255']);
        $withString = ProxyField::make('email')->rules('nullable|email|max:255');

        $this->assertSame(['nullable', 'email', 'max:255'], $withArray->validationRules());
        $this->assertSame(['nullable', 'email', 'max:255'], $withString->validationRules());
    }

    /**
     * toArray() отдаёт снапшот всех свойств поля для сериализации в API.
     * Проверяем ключевые поля; фильтруемый маркер включён.
     */
    public function test_to_array_returns_complete_field_snapshot(): void
    {
        $field = ProxyField::make('phone')
            ->label('Телефон')
            ->required()
            ->rules(['required', 'string'])
            ->example('+79990000000')
            ->filterable();

        $data = $field->toArray();

        $this->assertSame('phone', $data['key']);
        $this->assertSame('Телефон', $data['label']);
        $this->assertSame('payload.phone', $data['source']);
        $this->assertTrue($data['required']);
        $this->assertFalse($data['nullable']);
        $this->assertSame('+79990000000', $data['example']);
        $this->assertTrue($data['filterable']);
        $this->assertSame('phone', $data['filter_key']);
    }

    /**
     * Поле без filterable должно возвращать filter_key = null в toArray().
     */
    public function test_to_array_filter_key_is_null_when_not_filterable(): void
    {
        $field = ProxyField::make('comment');

        $this->assertNull($field->toArray()['filter_key']);
    }
}
