<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\auth\AccessTokenProviderInterface;
use dmstr\rest\sdk\auth\TokenProvider;
use dmstr\rest\sdk\exceptions\AuthenticationException;
use dmstr\rest\sdk\HttpClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\console\Application;

/**
 * Concrete HttpClient for testing purposes
 */
class TestableHttpClient extends HttpClient
{
    private ?GuzzleClient $testClient = null;

    public function setGuzzleClient(GuzzleClient $client): void
    {
        $this->testClient = $client;
    }

    protected function getClient(): GuzzleClient
    {
        return $this->testClient ?? parent::getClient();
    }
}

class HttpClientRetryTest extends TestCase
{
    private array $requestHistory = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (Yii::$app === null) {
            new Application(['id' => 'test', 'basePath' => __DIR__]);
        }
    }

    private function createClient(array $responses, mixed $tokenProvider = null): TestableHttpClient
    {
        $this->requestHistory = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->requestHistory));

        $guzzle = new GuzzleClient([
            'handler' => $stack,
            'http_errors' => false,
        ]);

        $client = new TestableHttpClient([
            'baseUri' => 'http://test.local',
        ]);
        $client->tokenProvider = $tokenProvider;
        $client->setGuzzleClient($guzzle);

        return $client;
    }

    public function testSuccessfulRequestWithToken(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{"data":"ok"}')],
            new TokenProvider(['token' => 'my-token'])
        );

        $result = $client->get('/test');

        $this->assertSame(['data' => 'ok'], $result);
        $this->assertCount(1, $this->requestHistory);
        $this->assertSame(
            'Bearer my-token',
            $this->requestHistory[0]['request']->getHeaderLine('Authorization')
        );
    }

    public function testNoAuthHeaderWithNullProvider(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{"data":"ok"}')],
            new TokenProvider()
        );

        $result = $client->get('/test');

        $this->assertSame(['data' => 'ok'], $result);
        $this->assertCount(1, $this->requestHistory);
        $this->assertEmpty($this->requestHistory[0]['request']->getHeaderLine('Authorization'));
    }

    public function testNoAuthHeaderWithNoProvider(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{"data":"ok"}')],
            null
        );

        $result = $client->get('/test');

        $this->assertSame(['data' => 'ok'], $result);
        $this->assertEmpty($this->requestHistory[0]['request']->getHeaderLine('Authorization'));
    }

    public function testCallableTokenProvider(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{"data":"ok"}')],
            fn () => new TokenProvider(['token' => 'from-callable'])
        );

        $result = $client->get('/test');

        $this->assertSame(['data' => 'ok'], $result);
        $this->assertSame(
            'Bearer from-callable',
            $this->requestHistory[0]['request']->getHeaderLine('Authorization')
        );
    }

    public function testCallableIsResolvedOnceAndCached(): void
    {
        $callCount = 0;
        $provider = function () use (&$callCount) {
            $callCount++;
            return new TokenProvider(['token' => 'cached-token']);
        };

        $client = $this->createClient(
            [
                new Response(200, [], '{"a":1}'),
                new Response(200, [], '{"b":2}'),
            ],
            $provider
        );

        $client->get('/first');
        $client->get('/second');

        $this->assertSame(1, $callCount);
    }

    public function testInvalidCallableReturnThrows(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{}')],
            fn () => 'not-a-provider'
        );

        $this->expectException(\yii\base\InvalidConfigException::class);
        $client->get('/test');
    }

    public function test401Throws(): void
    {
        $client = $this->createClient(
            [new Response(401, [], '{"message":"Unauthorized"}')],
            new TokenProvider(['token' => 'expired'])
        );

        $this->expectException(AuthenticationException::class);
        $client->get('/test');
    }

    public function testPostDelegatesToSendRequest(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{"id":1}')],
            new TokenProvider(['token' => 'tok'])
        );

        $result = $client->post('/items', ['json' => ['name' => 'test']]);

        $this->assertSame(['id' => 1], $result);
        $this->assertSame('POST', $this->requestHistory[0]['request']->getMethod());
    }

    public function testPatchDelegatesToSendRequest(): void
    {
        $client = $this->createClient(
            [new Response(200, [], '{}')],
            new TokenProvider(['token' => 'tok'])
        );

        $result = $client->patch('/items/1', ['json' => ['name' => 'updated']]);

        $this->assertTrue($result);
        $this->assertSame('PATCH', $this->requestHistory[0]['request']->getMethod());
    }

    public function testDeleteDelegatesToSendRequest(): void
    {
        $client = $this->createClient(
            [new Response(204, [], '{}')],
            new TokenProvider(['token' => 'tok'])
        );

        $result = $client->delete('/items/1');

        $this->assertTrue($result);
        $this->assertSame('DELETE', $this->requestHistory[0]['request']->getMethod());
    }
}
