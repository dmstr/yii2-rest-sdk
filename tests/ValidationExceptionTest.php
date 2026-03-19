<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\exceptions\ApiException;
use dmstr\rest\sdk\exceptions\AuthenticationException;
use dmstr\rest\sdk\exceptions\AuthorizationException;
use dmstr\rest\sdk\exceptions\HttpClientException;
use dmstr\rest\sdk\exceptions\NotFoundException;
use dmstr\rest\sdk\exceptions\ServerException;
use dmstr\rest\sdk\exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValidationExceptionTest extends TestCase
{
    public function testApiExceptionProperties(): void
    {
        $body = ['message' => 'Not found', 'name' => 'NotFound'];
        $e = new ApiException('Not found', 404, $body);

        self::assertSame(404, $e->getStatusCode());
        self::assertSame($body, $e->getResponseBody());
        self::assertSame('NotFound', $e->getApiErrorName());
        self::assertSame('Not found', $e->getApiMessage());
    }

    public function testApiExceptionWithMissingBodyKeys(): void
    {
        $e = new ApiException('Error', 500, []);

        self::assertNull($e->getApiErrorName());
        self::assertNull($e->getApiMessage());
    }

    public function testValidationExceptionFieldErrors(): void
    {
        $fieldErrors = [
            ['field' => 'title', 'message' => 'Title is required'],
            ['field' => 'title', 'message' => 'Title is too short'],
            ['field' => 'category_id', 'message' => 'Invalid category'],
        ];

        $e = new ValidationException('Validation failed', 422, [], $fieldErrors);

        self::assertSame($fieldErrors, $e->getFieldErrors());

        $grouped = $e->getErrorsByField();
        self::assertCount(2, $grouped);
        self::assertSame(['Title is required', 'Title is too short'], $grouped['title']);
        self::assertSame(['Invalid category'], $grouped['category_id']);
    }

    public function testValidationExceptionWithMalformedErrors(): void
    {
        $fieldErrors = [
            ['no_field_key' => true],
        ];

        $e = new ValidationException('Validation failed', 422, [], $fieldErrors);
        $grouped = $e->getErrorsByField();

        self::assertSame(['Validation error'], $grouped['unknown']);
    }

    public function testExceptionHierarchy(): void
    {
        self::assertInstanceOf(RuntimeException::class, new HttpClientException());
        self::assertInstanceOf(HttpClientException::class, new ApiException('test', 500));
        self::assertInstanceOf(ApiException::class, new AuthenticationException('test', 401));
        self::assertInstanceOf(ApiException::class, new AuthorizationException('test', 403));
        self::assertInstanceOf(ApiException::class, new NotFoundException('test', 404));
        self::assertInstanceOf(ApiException::class, new ServerException('test', 500));
        self::assertInstanceOf(ApiException::class, new ValidationException('test', 422));
    }
}
