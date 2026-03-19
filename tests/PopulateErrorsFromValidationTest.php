<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\exceptions\ValidationException;
use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\tests\fixtures\PostEntity;
use PHPUnit\Framework\TestCase;

class PopulateErrorsFromValidationTest extends TestCase
{
    public function testPopulateErrorsMapsApiKeysToPropertyNames(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $entity = new PostEntity($client);

        $fieldErrors = [
            ['field' => 'title', 'message' => 'Title is required'],
            ['field' => 'category_id', 'message' => 'Invalid category'],
        ];

        $exception = new ValidationException('Validation failed', 422, [], $fieldErrors);
        $entity->populateErrorsFromValidation($exception);

        // 'category_id' should be mapped to 'categoryId' property name
        self::assertTrue($entity->hasErrors('title'));
        self::assertTrue($entity->hasErrors('categoryId'));
        self::assertSame('Title is required', $entity->getFirstError('title'));
        self::assertSame('Invalid category', $entity->getFirstError('categoryId'));
    }

    public function testPopulateErrorsKeepsUnmappedFieldsAsIs(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $entity = new PostEntity($client);

        $fieldErrors = [
            ['field' => 'unknown_field', 'message' => 'Some error'],
        ];

        $exception = new ValidationException('Validation failed', 422, [], $fieldErrors);
        $entity->populateErrorsFromValidation($exception);

        self::assertTrue($entity->hasErrors('unknown_field'));
    }
}
