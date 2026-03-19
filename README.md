# Yii2 REST SDK

Typed entities, attribute mapping and resource abstraction for consuming REST APIs in Yii2 applications.

## Installation

Install the package via composer

```bash
composer require dmstr/yii2-rest-sdk
```

Requires PHP 8.2 or higher.

## Overview

The SDK provides a layered architecture for working with REST APIs:

- **HttpClient** handles authentication, caching and error handling on top of Guzzle
- **Resources** wrap API endpoints and provide a clean interface for CRUD operations
- **Entities** map API responses to typed PHP properties using PHP 8 attributes
- **Traits** for syncing entities with Yii2 ActiveRecord models

## Configuration

Register your HttpClient implementation as a Yii2 application component.

```php
return [
    'components' => [
        'blogApi' => [
            'class' => app\components\BlogClient::class,
            'baseUri' => getenv('API_BASE_URI'),
            'authClientId' => 'keycloak',
            'timeout' => 30,
            'cacheDuration' => 300,
        ],
    ],
];
```

The `authClientId` references a client from `yii\authclient\Collection`. The SDK uses it
to obtain a Bearer token via OpenID Connect for every request.

## Usage

### Defining an HttpClient

Extend the abstract `HttpClient` and add methods that return your resources.

```php
<?php

namespace app\components;

use dmstr\rest\sdk\HttpClient;
use app\resources\PostResource;
use app\resources\CommentResource;

class BlogClient extends HttpClient
{
    public function posts(): PostResource
    {
        return new PostResource($this);
    }

    public function comments(): CommentResource
    {
        return new CommentResource($this);
    }
}
```

### Defining a Resource

Resources wrap specific API endpoints. Extend `ReadonlyResource` for read-only endpoints
or `Resource` for full CRUD support.

```php
<?php

namespace app\resources;

use dmstr\rest\sdk\resources\Resource;
use app\entities\PostEntity;

class PostResource extends Resource
{
    protected array $cacheInvalidationPaths = ['/posts'];

    public function findAll(): array
    {
        $items = $this->get('/posts');
        return array_map(fn($data) => $this->hydrate($data), $items);
    }

    public function findOne(int $id, string $expand = ''): PostEntity
    {
        $params = $expand ? ['query' => ['expand' => $expand]] : [];
        $data = $this->get("/posts/$id", $params);
        return $this->hydrate($data);
    }

    public function update(PostEntity $entity): bool
    {
        $changes = $entity->updateAttributes();
        if (empty($changes)) {
            return true;
        }
        return $this->patch("/posts/{$entity->getId()}", ['json' => $changes]);
    }

    public function create(PostEntity $entity): PostEntity
    {
        $data = $this->post('/posts', ['json' => $entity->updateAttributes()]);
        return $this->hydrate($data);
    }

    private function hydrate(array $data): PostEntity
    {
        $entity = new PostEntity($this->client);
        $entity->setData($data);
        return $entity;
    }
}
```

### Defining an Entity

Entities are typed representations of API data. Use PHP 8 attributes to map properties
to API fields. Property names are automatically converted from camelCase to snake_case
unless you specify an explicit `apiKey`.

```php
<?php

namespace app\entities;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\attributes\Relation;
use dmstr\rest\sdk\attributes\ResourceType;
use dmstr\rest\sdk\entities\Entity;

#[ResourceType(resourceMethod: 'posts')]
class PostEntity extends Entity
{
    #[Property(readonly: true)]
    private int $id;

    #[Property(mutable: true)]
    private string $title;

    #[Property(mutable: true, apiKey: 'category_id')]
    private int $categoryId;

    #[Property(readonly: true)]
    private string $createdAt;

    #[Relation(
        entityClass: CommentEntity::class,
        expandKey: 'comments',
        multiple: true
    )]
    private array $comments = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getComments(): array
    {
        return $this->comments;
    }
}
```

### Property Attributes

The `#[Property]` attribute supports the following parameters:

- `mutable` (bool, default `false`): Property can be changed and sent back to the API
- `readonly` (bool, default `false`): Property is never included in change detection
- `apiKey` (string, optional): Explicit API field name, overrides the automatic camelCase to snake_case conversion
- `trackChanges` (bool, default `true`): Whether the property is included in change detection

### Relation Attributes

The `#[Relation]` attribute maps expanded API data to nested entities:

- `entityClass` (string): The fully qualified entity class name
- `expandKey` (string): The key in the API response that holds the related data
- `multiple` (bool, default `false`): Whether the relation is a collection

Relations are populated automatically when the corresponding expand key is present in the API response.

### Create, Update and Upsert

Entities provide `create()`, `update()` and `upsert()` methods that use the `#[ResourceType]` attribute
to resolve the responsible resource. Validation errors (HTTP 422) are caught and made available
through the error methods.

```php
$entity = new PostEntity($client);
$entity->setTitle('Hello World');
$entity->setCategoryId(3);

if (!$entity->create()) {
    // Validation failed
    $errors = $entity->getErrors();
    $firstErrors = $entity->getFirstErrors();
}
```

For updates, the SDK automatically detects which mutable properties have changed compared
to the original API data and only sends the diff.

```php
$entity = $client->posts()->findOne(1);
$entity->setTitle('Updated Title');

if (!$entity->update()) {
    $errors = $entity->getErrors();
}
```

`upsert()` delegates to `create()` or `update()` depending on whether the entity has an `id`.

### Error Handling

The HttpClient throws typed exceptions for different HTTP status codes:

- `AuthenticationException` for 401
- `AuthorizationException` for 403
- `NotFoundException` for 404
- `ValidationException` for 422 (with field-level errors)
- `ServerException` for 500
- `ApiException` for all other error codes

All exceptions extend `HttpClientException` which extends `RuntimeException`.

### Syncing with ActiveRecord

The `SyncsWithEntities` trait allows you to map entity properties to ActiveRecord attributes.
This is useful when you need to persist API data alongside local data.

```php
<?php

namespace app\models;

use dmstr\rest\sdk\traits\SyncsWithEntities;
use app\entities\PostEntity;
use yii\db\ActiveRecord;

class Post extends ActiveRecord
{
    use SyncsWithEntities;

    protected function entityMap(): array
    {
        return [
            PostEntity::class => [
                'title' => 'post_title',
                'categoryId' => 'category_id',
            ],
        ];
    }
}
```

**Reading from entities into the ActiveRecord model:**

```php
$entity = $client->posts()->findOne(1);
$model = new Post();
$model->populateFromEntities($entity);
```

**Pushing ActiveRecord values to entities and saving:**

```php
$model = Post::findOne(1);
$entity = $client->posts()->findOne($model->external_id);

if (!$model->syncToEntities($entity)) {
    $model->mergeEntityErrors($entity);
}
```

`mergeEntityErrors()` translates entity property names back to ActiveRecord attribute names
using the same mapping, so form validation messages display correctly.

### Caching

GET requests are cached automatically when a `cache` application component is available
and `cacheDuration` is greater than 0. Mutating operations (POST, PATCH, DELETE) in resources
can invalidate related cache entries through the `$cacheInvalidationPaths` property.

```php
class PostResource extends Resource
{
    protected array $cacheInvalidationPaths = ['/posts'];
}
```

You can also invalidate cache entries manually:

```php
$client->invalidateCache('/posts/1');
$client->invalidateCachePattern(['/posts', '/posts/1']);
```
