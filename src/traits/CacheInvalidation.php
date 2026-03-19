<?php

namespace dmstr\rest\sdk\traits;

use Yii;
use yii\caching\CacheInterface;

/**
 * Trait providing cache key management and invalidation helpers
 */
trait CacheInvalidation
{
    /**
     * Generate cache key for a given path
     */
    public function getCacheKey(string $path): string
    {
        return 'httpclient:' . md5(rtrim($this->baseUri, '/') . '/' . ltrim($path, '/'));
    }

    /**
     * Invalidate cache for a specific path
     */
    public function invalidateCache(string $path): bool
    {
        $cache = $this->getAvailableCache();
        if ($cache === null) {
            return false;
        }
        $cacheKey = $this->getCacheKey($path);
        return $cache->delete($cacheKey);
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
     * Invalidate cache by pattern (multiple paths)
     * For simple cases, just invalidate specific known paths
     */
    public function invalidateCachePattern(array $paths): bool
    {
        $cache = $this->getAvailableCache();
        if ($cache === null) {
            return false;
        }
        $success = true;

        foreach ($paths as $path) {
            $cacheKey = $this->getCacheKey($path);
            $success = $cache->delete($cacheKey) && $success;
        }

        return $success;
    }
}
