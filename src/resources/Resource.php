<?php

namespace dmstr\rest\sdk\resources;

class Resource extends ReadonlyResource
{
    /**
     * Paths that should have cache invalidated after mutations
     * Can be overridden in child classes to specify related paths
     */
    protected array $cacheInvalidationPaths = [];

    protected function patch(string $path, array $options = []): true
    {
        $this->client->patch($path, $options);
        $this->invalidateRelatedCaches();

        return true;
    }

    protected function post(string $path, array $options = []): array
    {
        $data = $this->client->post($path, $options);
        $this->invalidateRelatedCaches();

        return $data;
    }

    protected function delete(string $path, array $options = []): true
    {
        $this->client->delete($path, $options);
        $this->invalidateRelatedCaches();

        return true;
    }

    private function invalidateRelatedCaches(): void
    {
        if (!empty($this->cacheInvalidationPaths)) {
            $this->client->invalidateCachePattern($this->cacheInvalidationPaths);
        }
    }
}
