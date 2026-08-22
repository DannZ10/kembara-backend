<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper over the cache that uses Redis tag invalidation when the store
 * supports it (redis/array/memcached) and falls back to a plain TTL cache on
 * non-taggable stores (file/database), so local dev without Redis still runs.
 */
final class CacheHelper
{
    public const CATALOG = 'catalog';
    public const REPORTS = 'reports';

    public static function remember(string $tag, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (self::taggable()) {
            return Cache::tags($tag)->remember($key, $ttlSeconds, $callback);
        }

        return Cache::remember("{$tag}:{$key}", $ttlSeconds, $callback);
    }

    /**
     * Invalidate a whole tag. No-op on non-taggable stores (entries expire by TTL).
     */
    public static function flush(string ...$tags): void
    {
        if (! self::taggable()) {
            return;
        }

        foreach ($tags as $tag) {
            Cache::tags($tag)->flush();
        }
    }

    private static function taggable(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
