<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\tests\fixtures\PostEntity;
use PHPUnit\Framework\TestCase;

class HasErrorsTest extends TestCase
{
    private PostEntity $entity;

    protected function setUp(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->entity = new PostEntity($client);
    }

    public function testNoErrorsByDefault(): void
    {
        self::assertFalse($this->entity->hasErrors());
        self::assertEmpty($this->entity->getErrors());
    }

    public function testAddError(): void
    {
        $this->entity->addError('title', 'Title is required');

        self::assertTrue($this->entity->hasErrors());
        self::assertTrue($this->entity->hasErrors('title'));
        self::assertFalse($this->entity->hasErrors('categoryId'));
    }

    public function testGetErrors(): void
    {
        $this->entity->addError('title', 'Too short');
        $this->entity->addError('title', 'Contains invalid characters');
        $this->entity->addError('categoryId', 'Invalid category');

        $allErrors = $this->entity->getErrors();
        self::assertCount(2, $allErrors);
        self::assertCount(2, $allErrors['title']);
        self::assertCount(1, $allErrors['categoryId']);

        $titleErrors = $this->entity->getErrors('title');
        self::assertSame(['Too short', 'Contains invalid characters'], $titleErrors);

        $noErrors = $this->entity->getErrors('nonExistent');
        self::assertEmpty($noErrors);
    }

    public function testGetFirstErrors(): void
    {
        $this->entity->addError('title', 'First title error');
        $this->entity->addError('title', 'Second title error');
        $this->entity->addError('categoryId', 'Category error');

        $firstErrors = $this->entity->getFirstErrors();

        self::assertSame('First title error', $firstErrors['title']);
        self::assertSame('Category error', $firstErrors['categoryId']);
    }

    public function testGetFirstError(): void
    {
        $this->entity->addError('title', 'First');
        $this->entity->addError('title', 'Second');

        self::assertSame('First', $this->entity->getFirstError('title'));
        self::assertNull($this->entity->getFirstError('nonExistent'));
    }

    public function testClearAllErrors(): void
    {
        $this->entity->addError('title', 'Error 1');
        $this->entity->addError('categoryId', 'Error 2');

        $this->entity->clearErrors();

        self::assertFalse($this->entity->hasErrors());
    }

    public function testClearErrorsForAttribute(): void
    {
        $this->entity->addError('title', 'Error 1');
        $this->entity->addError('categoryId', 'Error 2');

        $this->entity->clearErrors('title');

        self::assertFalse($this->entity->hasErrors('title'));
        self::assertTrue($this->entity->hasErrors('categoryId'));
    }
}
