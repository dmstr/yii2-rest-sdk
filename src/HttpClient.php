<?php

namespace dmstr\rest\sdk;

use dmstr\rest\sdk\auth\AccessTokenProviderInterface;
use dmstr\rest\sdk\exceptions\ApiException;
use dmstr\rest\sdk\exceptions\AuthenticationException;
use dmstr\rest\sdk\exceptions\AuthorizationException;
use dmstr\rest\sdk\exceptions\NotFoundException;
use dmstr\rest\sdk\exceptions\ServerException;
use dmstr\rest\sdk\exceptions\ValidationException;
use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\traits\CacheInvalidation;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Yii;
use yii\base\Component;

abstract class HttpClient extends Component implements HttpClientInterface
{
    use CacheInvalidation;

    /**
     * Base URI for client
     */
    public string $baseUri;

    /**
     * Request timeout in seconds
     */
    public int $timeout = 30;

    /**
     * Whether to verify SSL certificates
     */
    public bool $verifySSL = true;

    /**
     * Cache duration in seconds (0 to disable caching)
     */
    public int $cacheDuration = 300;

    /**
     * HTTP client instance
     */
    protected ?GuzzleClient $_client = null;

    /**
     * Token provider for authentication.
     * Accepts an AccessTokenProviderInterface instance or a callable that returns one.
     * When null, requests are sent without Authorization header.
     *
     * @var AccessTokenProviderInterface|callable|null
     */
    public mixed $tokenProvider = null;

    public string $userAgent = 'REST/1.0';

    /**
     * Get the Guzzle HTTP client instance
     *
     * @return GuzzleClient
     */
    protected function getClient(): GuzzleClient
    {
        if ($this->_client === null) {
            $this->_client = new GuzzleClient($this->clientConfig());
        }
        return $this->_client;
    }

    /**
     * Resolves $tokenProvider (callable invoked once, then cached) and returns the instance.
     */
    protected function resolveTokenProvider(): ?AccessTokenProviderInterface
    {
        if ($this->tokenProvider === null) {
            return null;
        }

        if (is_callable($this->tokenProvider)) {
            $this->tokenProvider = ($this->tokenProvider)();
        }

        if (!$this->tokenProvider instanceof AccessTokenProviderInterface) {
            throw new \yii\base\InvalidConfigException(
                'tokenProvider must be an AccessTokenProviderInterface instance or a callable returning one'
            );
        }

        return $this->tokenProvider;
    }

    protected function getAccessToken(): ?string
    {
        return $this->resolveTokenProvider()?->getAccessToken();
    }

    protected function clientConfig(): array
    {
        return [
            'base_uri' => rtrim($this->baseUri, '/'),
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::CONNECT_TIMEOUT => 5, // Fast fail on connection issues
            RequestOptions::VERIFY => $this->verifySSL,
            RequestOptions::HTTP_ERRORS => false, // Handle errors manually
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'User-Agent' => $this->userAgent,
            ],
        ];
    }

    /**
     * Merge request options with per-request auth header.
     * Resolves the access token at request time to avoid stale tokens.
     * Only adds Authorization header when a token is available.
     */
    protected function requestOptions(array $options = []): array
    {
        $token = $this->getAccessToken();
        if ($token !== null) {
            $options[RequestOptions::HEADERS] = array_merge(
                $options[RequestOptions::HEADERS] ?? [],
                ['Authorization' => 'Bearer ' . $token]
            );
        }
        return $options;
    }

    /**
     * @throws ApiException
     */
    protected function sendRequest(string $method, string $path, array $options = []): array
    {
        $response = $this->getClient()->request($method, $path, $this->requestOptions($options));
        return $this->handleResponse($response, $path);
    }

    public function get(string $path, array $options = []): array
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($path);
        $cache = $this->getAvailableCache();

        if ($cache && $this->cacheDuration > 0) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $data = $this->sendRequest('GET', $path, $options);

        // Write to cache
        if ($cache && $this->cacheDuration > 0) {
            $cache->set($cacheKey, $data, $this->cacheDuration);
        }

        return $data;
    }

    public function patch(string $path, array $options): true
    {
        $this->sendRequest('PATCH', $path, $options);
        return true;
    }

    public function post(string $path, array $options): array
    {
        return $this->sendRequest('POST', $path, $options);
    }

    public function delete(string $path, array $options = []): true
    {
        $this->sendRequest('DELETE', $path, $options);
        return true;
    }

    /**
     * Handle API response: return decoded body on success, throw typed exceptions on error
     *
     * @throws ApiException
     */
    protected function handleResponse(ResponseInterface $response, string $path): array
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true) ?: [];

        if ($statusCode >= 200 && $statusCode < 300) {
            return $body;
        }

        Yii::error(['status' => $statusCode, 'path' => $path, 'response' => $body], __METHOD__);

        // 422 returns an array of [{field, message}, ...] — no top-level 'message' key
        if ($statusCode === 422) {
            $fieldErrors = array_is_list($body) ? $body : [];
            throw new ValidationException(
                "Validation failed: $path returned 422",
                $statusCode,
                $body,
                $fieldErrors
            );
        }

        $message = $body['message'] ?? "REST API request failed: $path returned $statusCode";

        throw match ($statusCode) {
            401 => new AuthenticationException($message, $statusCode, $body),
            403 => new AuthorizationException($message, $statusCode, $body),
            404 => new NotFoundException($message, $statusCode, $body),
            500 => new ServerException($message, $statusCode, $body),
            default => new ApiException($message, $statusCode, $body),
        };
    }
}
