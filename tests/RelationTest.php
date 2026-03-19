<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\tests\fixtures\PostEntity;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    private HttpClientInterface $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HttpClientInterface::class);
    }

    public function testMultipleRelationMapping(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Test',
            'comments' => [
                ['id' => 10, 'body' => 'First comment'],
                ['id' => 11, 'body' => 'Second comment'],
            ],
        ]);

        $comments = $entity->getComments();

        self::assertCount(2, $comments);
        self::assertSame(10, $comments[0]->getId());
        self::assertSame('First comment', $comments[0]->getBody());
        self::assertSame(11, $comments[1]->getId());
        self::assertSame('Second comment', $comments[1]->getBody());
    }

    public function testSingleRelationMapping(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Test',
            'author' => ['id' => 5, 'name' => 'Jane'],
        ]);

        $author = $entity->getAuthor();

        self::assertNotNull($author);
        self::assertSame(5, $author->getId());
        self::assertSame('Jane', $author->getName());
    }

    public function testRelationNotPresentInData(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'title' => 'Test',
        ]);

        self::assertEmpty($entity->getComments());
        self::assertNull($entity->getAuthor());
    }

    public function testHasRelation(): void
    {
        $entity = new PostEntity($this->client);

        self::assertTrue($entity->hasRelation('comments'));
        self::assertTrue($entity->hasRelation('author'));
        self::assertFalse($entity->hasRelation('nonExistent'));
    }

    public function testGetRelation(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'author' => ['id' => 3, 'name' => 'John'],
        ]);

        $author = $entity->getRelation('author');
        self::assertSame(3, $author->getId());

        self::assertNull($entity->getRelation('nonExistent'));
    }

    public function testGetExpandParams(): void
    {
        $entity = new PostEntity($this->client);
        $params = $entity->getExpandParams();

        self::assertStringContainsString('comments', $params);
        self::assertStringContainsString('author', $params);
    }

    public function testRelatedEntitiesReceiveClient(): void
    {
        $entity = new PostEntity($this->client);
        $entity->setData([
            'id' => 1,
            'comments' => [
                ['id' => 10, 'body' => 'Test'],
            ],
            'author' => ['id' => 5, 'name' => 'Jane'],
        ]);

        $comment = $entity->getComments()[0];
        $author = $entity->getAuthor();

        self::assertSame($this->client, $comment->getClient());
        self::assertSame($this->client, $author->getClient());
    }
}
