<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Directories\Services\Importing\DirectoryImportCoordinator;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class DictionaryApiSyncService
{
    public function __construct(
        private DirectoryVersionRepository $versions,
        private DirectoryImportCoordinator $imports,
        private DirectoryApiConfigurationValidator $configuration,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function queue(
        Directory $directory,
        ?int $userId = null,
        ?DirectoryImportOptions $options = null,
    ): DirectoryImport {
        if ($directory->source_type !== 'api') {
            throw DictionaryApiSyncException::notApiDirectory();
        }

        $proxyUuid = $directory->api_config_json['proxy_uuid'] ?? null;

        if (!is_string($proxyUuid) || $proxyUuid === '') {
            throw DictionaryApiSyncException::proxyNotConfigured();
        }

        $endpoint = ProxyEndpoint::query()->where('uuid', $proxyUuid)->first();

        if ($endpoint === null) {
            throw DictionaryApiSyncException::proxyNotFound($proxyUuid);
        }

        $version = $this->versions->activeOrFirst($directory)
            ?? DB::transaction(fn() => $this->versions->createNext($directory, null));

        $this->configuration->validateDirectory($directory);

        $directory->forceFill(['sync_status' => 'queued', 'sync_error' => null])->save();

        return $this->imports->queueProxy(
            directory: $directory,
            version: $version,
            endpoint: $endpoint,
            options: $options ?? DirectoryImportOptions::fromVersion($version),
            userId: $userId,
        );
    }

    public function nextSyncAt(Directory $directory): CarbonInterface
    {
        $refreshInterval = $directory->api_config_json['refresh_interval'] ?? null;
        $seconds = max(60, is_int($refreshInterval) ? $refreshInterval : 3600);

        return now()->addSeconds($seconds);
    }
}
