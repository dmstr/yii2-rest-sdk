<?php

namespace dmstr\rest\sdk\interfaces;

interface HttpClientInterface
{
    public function get(string $path, array $options = []): array;

    public function patch(string $path, array $options): true;

    public function post(string $path, array $options): array;

    public function delete(string $path, array $options = []): true;

    public function invalidateCache(string $path): bool;

    public function invalidateCachePattern(array $paths): bool;
}
