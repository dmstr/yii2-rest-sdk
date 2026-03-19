<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\tests\fixtures\AuthorEntity;
use dmstr\rest\sdk\tests\fixtures\CommentEntity;
use dmstr\rest\sdk\tests\fixtures\EntityWithoutResource;
use dmstr\rest\sdk\tests\fixtures\PostEntity;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EntityTest extends TestCase
{
    private HttpClientInterface $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HttpClientInterface::class);
    }

    public function testSetDataMapsProperties(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Hello World',
            'category_id' => 5,
            'created_at' => '2025-01-01',
        ]);

        self::assertSame(1, $entity->getId());
        self::assertSame('Hello World', $entity->getTitle());
        self::assertSame(5, $entity->getCategoryId());
        self::assertSame('2025-01-01', $entity->getCreatedAt());
    }

    public function testSetDataCoercesTypes(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => '42',
            'title' => 123,
            'category_id' => '7',
        ]);

        self::assertSame(42, $entity->getId());
        self::assertSame('123', $entity->getTitle());
        self::assertSame(7, $entity->getCategoryId());
    }

    public function testSetDataIgnoresMissingKeys(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData(['id' => 1]);

        self::assertSame(1, $entity->getId());
        self::assertSame('', $entity->getTitle());
    }

    public function testJsonSerializeReturnsRawData(): void
    {
        $data = ['id' => 1, 'title' => 'Test', 'category_id' => 3];
        $entity = new PostEntity($this->client);
        $entity->setData($data);

        self::assertSame($data, $entity->jsonSerialize());
    }

    public function testUpdateAttributesDetectsChanges(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Original',
            'category_id' => 5,
        ]);

        $entity->setTitle('Changed');

        $changes = $entity->updateAttributes();

        self::assertSame(['title' => 'Changed'], $changes);
    }

    public function testUpdateAttributesIgnoresReadonly(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Same',
            'category_id' => 5,
        ]);

        // No mutable field changed
        $changes = $entity->updateAttributes();

        self::assertEmpty($changes);
        self::assertArrayNotHasKey('id', $changes);
        self::assertArrayNotHasKey('created_at', $changes);
    }

    public function testUpdateAttributesIgnoresTrackChangesFalse(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'slug' => 'original-slug',
        ]);

        $entity->setSlug('new-slug');

        $changes = $entity->updateAttributes();

        self::assertArrayNotHasKey('slug', $changes);
    }

    public function testUpdateAttributesDetectsMultipleChanges(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Old',
            'category_id' => 5,
        ]);

        $entity->setTitle('New');
        $entity->setCategoryId(10);

        $changes = $entity->updateAttributes();

        self::assertSame('New', $changes['title']);
        self::assertSame(10, $changes['category_id']);
        self::assertCount(2, $changes);
    }

    public function testGetClientReturnsInjectedClient(): void
    {
        $entity = new PostEntity($this->client);

        self::assertSame($this->client, $entity->getClient());
    }

    public function testGetApiKeyToPropertyMap(): void
    {
        $entity = new PostEntity($this->client);
        $map = $entity->getApiKeyToPropertyMap();

        self::assertSame('id', $map['id']);
        self::assertSame('title', $map['title']);
        self::assertSame('categoryId', $map['category_id']);
        self::assertSame('createdAt', $map['created_at']);
    }

    public function testUpsertWithoutIdCallsCreate(): void
    {
        $entity = new PostEntity($this->client);
        // No data set, so no 'id' in _data

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have resource method: posts');

        // PostEntity has ResourceType('posts') but client mock has no posts() method.
        // The error proves upsert() took the create() path through getResource().
        $entity->upsert();
    }

    public function testEntityWithoutResourceTypeThrows(): void
    {
        $entity = new EntityWithoutResource($this->client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must have ResourceType attribute');

        $entity->create();
    }
}
