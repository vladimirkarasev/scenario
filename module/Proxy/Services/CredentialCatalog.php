<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use InvalidArgumentException;
use Module\Proxy\Credentials\AutoCrm\AutoCrmCredential;
use Module\Proxy\Credentials\BasicCredential;
use Module\Proxy\Credentials\BearerCredential;
use Module\Proxy\Credentials\DaData\DaDataCredential;
use Module\Proxy\Credentials\ProxyCredential;

final readonly class CredentialCatalog
{
    /** @var array<class-string<ProxyCredential>, ProxyCredential> */
    private array $credentials;

    public function __construct(
        AutoCrmCredential $autoCrm,
        BearerCredential $bearer,
        BasicCredential $basic,
        DaDataCredential $daData,
    ) {
        $this->credentials = [
            $autoCrm::class => $autoCrm,
            $bearer::class => $bearer,
            $basic::class => $basic,
            $daData::class => $daData,
        ];
    }

    /** @return list<class-string<ProxyCredential>> */
    public function all(): array
    {
        return array_keys($this->credentials);
    }

    public function has(string $class): bool
    {
        return isset($this->credentials[$class]);
    }

    public function get(string $class): ProxyCredential
    {
        if (! $this->has($class)) {
            throw new InvalidArgumentException('Unknown credential type: '.$class);
        }

        return $this->credentials[$class];
    }

    /** @return array<int, array{type: string, label: string, group: string, fields: list<array<string, mixed>>}> */
    public function options(): array
    {
        $options = [];
        foreach ($this->credentials as $class => $driver) {
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
