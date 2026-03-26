<?php

namespace dmstr\rest\sdk\interfaces;

/**
 * HTTP client interface for REST API communication.
 *
 * GET responses are cached automatically. Mutating methods (PATCH, POST, DELETE)
 * invalidate the cache for their request path after a successful response.
 *
 * Cache entries are tagged hierarchically — invalidating a parent path
 * (e.g. /posts) also clears all child entries (e.g. /posts/1).
 */
interface HttpClientInterface
{
    /**
     * Send a GET request. Returns cached response if available.
     * Options are included in the cache key so different query parameters
     * produce independent cache entries.
     *
     * @param string $path API endpoint path
     * @param array $options Guzzle request options (e.g. ['query' => ['expand' => 'comments']])
     */
    public function get(string $path, array $options = []): array;

    /**
     * Send a PATCH request and invalidate cache for the given path.
     */
    public function patch(string $path, array $options): true;

    /**
     * Send a POST request and invalidate cache for the given path.
     */
    public function post(string $path, array $options): array;

    /**
     * Send a DELETE request and invalidate cache for the given path.
     */
    public function delete(string $path, array $options = []): true;

    /**
     * Invalidate all cached entries for the given path and its parameter variants.
     * Due to hierarchical tagging, this also clears child paths.
     *
     * Example: invalidateCache('/posts') clears /posts, /posts/1, /posts/1?expand=x, etc.
     */
    public function invalidateCache(string $path): bool;

    /**
     * Invalidate cache for multiple paths at once.
     * Typically used by resources to clear related endpoints after mutations.
     *
     * @param string[] $paths List of API endpoint paths to invalidate
     */
    public function invalidateCachePattern(array $paths): bool;
}
