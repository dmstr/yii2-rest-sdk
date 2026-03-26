<?php

namespace dmstr\rest\sdk\tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\caching\ArrayCache;
use yii\console\Application;

class HttpClientCacheTest extends TestCase
{
    private array $requestHistory = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh Yii app with ArrayCache for each test
        new Application([
            'id' => 'test-cache',
            'basePath' => __DIR__,
            'components' => [
                'cache' => ['class' => ArrayCache::class],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Yii::$app = null;
    }

    private function createClient(array $responses, int $cacheDuration = 300): TestableHttpClient
    {
        $this->requestHistory = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->requestHistory));

        $guzzle = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);

        $client = new TestableHttpClient(['baseUri' => 'http://test.local']);
        $client->cacheDuration = $cacheDuration;
        $client->setGuzzleClient($guzzle);

        return $client;
    }

    // --- Cache key tests ---

    public function testCacheKeyIncludesOptions(): void
    {
        $client = $this->createClient([]);

        $keyA = $client->getCacheKey('/posts', []);
        $keyB = $client->getCacheKey('/posts', ['query' => ['expand' => 'comments']]);

        $this->assertNotSame($keyA, $keyB);
    }

    public function testCacheKeyStableForEmptyOptions(): void
    {
        $client = $this->createClient([]);

        $this->assertSame(
            $client->getCacheKey('/posts'),
            $client->getCacheKey('/posts', [])
        );
    }

    public function testCacheKeyDiffersForDifferentParams(): void
    {
        $client = $this->createClient([]);

        $keyA = $client->getCacheKey('/posts', ['query' => ['expand' => 'a']]);
        $keyB = $client->getCacheKey('/posts', ['query' => ['expand' => 'b']]);

        $this->assertNotSame($keyA, $keyB);
    }

    // --- Hierarchical tag tests ---

    public function testGetCacheTagsReturnsHierarchy(): void
    {
        $client = $this->createClient([]);

        $tags = $client->getCacheTags('api/v1/posts/123');

        $this->assertCount(4, $tags);
        $this->assertSame($client->getCacheTag('api'), $tags[0]);
        $this->assertSame($client->getCacheTag('api/v1'), $tags[1]);
        $this->assertSame($client->getCacheTag('api/v1/posts'), $tags[2]);
        $this->assertSame($client->getCacheTag('api/v1/posts/123'), $tags[3]);
    }

    public function testGetCacheTagsSingleSegment(): void
    {
        $client = $this->createClient([]);

        $tags = $client->getCacheTags('posts');

        $this->assertCount(1, $tags);
        $this->assertSame($client->getCacheTag('posts'), $tags[0]);
    }

    // --- GET caching tests ---

    public function testGetCachesResponse(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"data":"first"}'),
        ]);

        $first = $client->get('/posts');
        $second = $client->get('/posts');

        $this->assertSame(['data' => 'first'], $first);
        $this->assertSame(['data' => 'first'], $second);
        $this->assertCount(1, $this->requestHistory, 'Second GET should come from cache');
    }

    public function testDifferentParamsCacheSeparately(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"type":"no-expand"}'),
            new Response(200, [], '{"type":"with-expand"}'),
        ]);

        $a = $client->get('/posts', []);
        $b = $client->get('/posts', ['query' => ['expand' => 'comments']]);

        $this->assertSame(['type' => 'no-expand'], $a);
        $this->assertSame(['type' => 'with-expand'], $b);
        $this->assertCount(2, $this->requestHistory, 'Different params should trigger separate requests');
    }

    public function testCacheDisabledWhenDurationZero(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"call":1}'),
            new Response(200, [], '{"call":2}'),
        ], 0);

        $first = $client->get('/posts');
        $second = $client->get('/posts');

        $this->assertSame(['call' => 1], $first);
        $this->assertSame(['call' => 2], $second);
        $this->assertCount(2, $this->requestHistory);
    }

    // --- Mutation invalidation tests ---

    public function testPatchInvalidatesCache(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"title":"old"}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{"title":"new"}'),
        ]);

        $first = $client->get('/posts/1');
        $client->patch('/posts/1', ['json' => ['title' => 'new']]);
        $after = $client->get('/posts/1');

        $this->assertSame(['title' => 'old'], $first);
        $this->assertSame(['title' => 'new'], $after);
        $this->assertCount(3, $this->requestHistory, 'GET after PATCH should not come from cache');
    }

    public function testPostInvalidatesCache(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"id":1}]'),
            new Response(200, [], '{"id":2}'),
            new Response(200, [], '[{"id":1},{"id":2}]'),
        ]);

        $client->get('/posts');
        $client->post('/posts', ['json' => ['title' => 'new']]);
        $after = $client->get('/posts');

        $this->assertSame([['id' => 1], ['id' => 2]], $after);
        $this->assertCount(3, $this->requestHistory);
    }

    public function testDeleteInvalidatesCache(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id":1}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{"id":1}'),
        ]);

        $client->get('/posts/1');
        $client->delete('/posts/1');
        $client->get('/posts/1');

        $this->assertCount(3, $this->requestHistory, 'GET after DELETE should not come from cache');
    }

    // --- Hierarchical invalidation tests ---

    public function testParentInvalidationCascadesToChild(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"title":"old"}'),
            new Response(200, [], '{"title":"fresh"}'),
        ]);

        $client->get('/posts/1');
        $client->invalidateCache('/posts');
        $after = $client->get('/posts/1');

        $this->assertSame(['title' => 'fresh'], $after);
        $this->assertCount(2, $this->requestHistory, 'Child cache should be cleared by parent invalidation');
    }

    public function testParentInvalidationClearsAllChildren(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id":1}'),
            new Response(200, [], '{"id":2}'),
            new Response(200, [], '{"id":1,"v":2}'),
            new Response(200, [], '{"id":2,"v":2}'),
        ]);

        $client->get('/posts/1');
        $client->get('/posts/2');
        $client->invalidateCache('/posts');
        $a = $client->get('/posts/1');
        $b = $client->get('/posts/2');

        $this->assertSame(['id' => 1, 'v' => 2], $a);
        $this->assertSame(['id' => 2, 'v' => 2], $b);
        $this->assertCount(4, $this->requestHistory);
    }

    public function testChildInvalidationDoesNotAffectSibling(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id":1}'),
            new Response(200, [], '{"id":2}'),
        ]);

        $client->get('/posts/1');
        $client->get('/posts/2');
        $client->invalidateCache('/posts/1');

        // posts/2 should still be cached
        $result = $client->get('/posts/2');
        $this->assertSame(['id' => 2], $result);
        $this->assertCount(2, $this->requestHistory, 'Sibling cache should not be affected');
    }

    public function testParentInvalidationClearsChildWithExpandParams(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"name":"old","kontakt":{"phone":"111"}}'),
            new Response(200, [], '{"name":"old","kontakt":{"phone":"222"}}'),
        ]);

        $client->get('/person/1', ['query' => ['expand' => 'kontakt']]);

        // Invalidating /person clears /person/1 due to hierarchical tags
        $client->invalidateCache('/person');

        $after = $client->get('/person/1', ['query' => ['expand' => 'kontakt']]);

        $this->assertSame('222', $after['kontakt']['phone']);
        $this->assertCount(2, $this->requestHistory);
    }
}
