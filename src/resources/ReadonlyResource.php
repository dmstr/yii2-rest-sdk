<?php

namespace dmstr\rest\sdk\resources;

use dmstr\rest\sdk\interfaces\HttpClientInterface;

abstract class ReadonlyResource
{
    protected HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    protected function get(string $path, array $options = []): array
    {
        return $this->client->get($path, $options);
    }
}
