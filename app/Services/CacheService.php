<?php

namespace App\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Check if the current cache store supports tagging
     */
    public static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    /**
     * Get a tagged cache if supported, otherwise return the default cache
     */
    public static function tags(array $tags)
    {
        if (self::supportsTags()) {
            return Cache::tags($tags);
        }

        return Cache::store();
    }

    /**
     * Clear all article-related caches
     */
    public static function clearArticleCaches(): void
    {
        if (self::supportsTags()) {
            Cache::tags(['articles'])->flush();
        } else {
            // For drivers without tags, we can't easily clear specific groups
            // We could use a versioning system for keys, but for now we might
            // just have to wait for expiration or clear all (too aggressive)
        }
    }

    /**
     * Clear trending-specific caches
     */
    public static function clearTrendingCaches(): void
    {
        if (self::supportsTags()) {
            Cache::tags(['articles', 'trending'])->flush();
        }
    }

    /**
     * Get cache key for trending articles
     */
    public static function trendingKey(string $period, int $limit, array $categories = []): string
    {
        return "trending_articles_{$period}_{$limit}_" . md5(json_encode($categories));
    }
}
