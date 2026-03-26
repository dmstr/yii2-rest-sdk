<?php

namespace dmstr\rest\sdk\traits;

use Yii;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;

/**
 * Trait providing cache key management and invalidation helpers
 */
trait CacheInvalidation
{
    /**
     * Generate cache key for a given path and options.
     * Options (e.g. query parameters) are included so that different
     * parameter combinations produce distinct cache entries.
     */
    public function getCacheKey(string $path, array $options = []): string
    {
        $normalized = rtrim($this->baseUri, '/') . '/' . ltrim($path, '/');
        return 'httpclient:' . md5($normalized . serialize($options));
    }

    /**
     * Generate a cache tag for a given path (ignoring options).
     * Used to group all parameter variants of the same endpoint
     * so they can be invalidated together.
     */
    public function getCacheTag(string $path): string
    {
        return 'httpclient:tag:' . md5(rtrim($this->baseUri, '/') . '/' . ltrim($path, '/'));
    }

    /**
     * Generate hierarchical cache tags for a path.
     * Returns tags for the path and all parent paths, so invalidating
     * a parent (e.g. /person) also clears children (e.g. /person/123).
     */
    public function getCacheTags(string $path): array
    {
        $segments = explode('/', trim($path, '/'));
        $tags = [];
        $current = '';
        foreach ($segments as $segment) {
            $current .= ($current ? '/' : '') . $segment;
            $tags[] = $this->getCacheTag($current);
        }
        return $tags;
    }

    /**
     * Invalidate all cached variants for a specific path
     */
    public function invalidateCache(string $path): bool
    {
        $cache = $this->getAvailableCache();
        if ($cache === null) {
            return false;
        }
        TagDependency::invalidate($cache, [$this->getCacheTag($path)]);
        return true;
    }

    private function getAvailableCache(): ?CacheInterface
    {
        $id = $this->cacheComponent ?? 'cache';
        if (!Yii::$app->has($id)) {
            return null;
        }
        $component = Yii::$app->get($id);
        return $component instanceof CacheInterface ? $component : null;
    }

    /**
     * Invalidate cache for multiple paths at once.
     * Clears all parameter variants for each path.
     */
    public function invalidateCachePattern(array $paths): bool
    {
        $cache = $this->getAvailableCache();
        if ($cache === null) {
            return false;
        }
        $tags = array_map(fn(string $path) => $this->getCacheTag($path), $paths);
        TagDependency::invalidate($cache, $tags);
        return true;
    }
}
