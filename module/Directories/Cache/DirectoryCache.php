<?php

declare(strict_types=1);

namespace Module\Directories\Cache;

use Illuminate\Support\Facades\Cache;

final class DirectoryCache
{
    private const TTL_LIST = 300;

    private const TTL_DETAIL = 600;

    /**
     * @template T
     *
     * @param  \Closure(): T $callback
     * @return T
     */
    public static function rememberList(string $projectId, \Closure $callback): mixed
    {
        return Cache::remember(self::listKey($projectId), self::TTL_LIST, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T $callback
     * @return T
     */
    public static function rememberDetail(string $directoryId, \Closure $callback): mixed
    {
        return Cache::remember(self::detailKey($directoryId), self::TTL_DETAIL, $callback);
    }

    public static function forgetDirectory(string $directoryId): void
    {
        Cache::forget(self::detailKey($directoryId));
    }

    public static function forgetList(string $projectId): void
    {
        Cache::forget(self::listKey($projectId));
    }

    private static function listKey(string $projectId): string
    {
        return "directories.list.{$projectId}";
    }

    private static function detailKey(string $directoryId): string
    {
        return "directory.{$directoryId}";
    }
}
