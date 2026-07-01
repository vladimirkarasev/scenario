<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use InvalidArgumentException;
use Module\Proxy\Credentials\AutoCrm\AutoCrmCredential;
use Module\Proxy\Credentials\BasicCredential;
use Module\Proxy\Credentials\BearerCredential;
use Module\Proxy\Credentials\ProxyCredential;

/**
 * Каталог драйверов доступа (connection). Новый тип доступа = добавить класс сюда.
 * Реестр заодно служит whitelist-ом: привязать можно только зарегистрированный тип.
 */
final class CredentialCatalog
{
    private function __construct() {}

    /** @return list<class-string<ProxyCredential>> */
    public static function all(): array
    {
        return [
            AutoCrmCredential::class,
            BearerCredential::class,
            BasicCredential::class,
        ];
    }

    public static function has(string $class): bool
    {
        return in_array($class, self::all(), true);
    }

    /** Инстанцирует драйвер по class-string (с проверкой по реестру). */
    public static function make(string $class): ProxyCredential
    {
        if (! self::has($class)) {
            throw new InvalidArgumentException('Unknown credential type: '.$class);
        }

        /** @var ProxyCredential */
        return app($class);
    }

    /** @return array<int, array{type: string, label: string, group: string, fields: list<array<string, mixed>>}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $class) {
            $driver = self::make($class);
            $options[] = [
                'type' => $class,
                'label' => $driver->label(),
                'group' => $driver->group(),
                'fields' => $driver->fieldSchema(),
            ];
        }

        return $options;
    }
}
