<?php

namespace dmstr\rest\sdk\tests\fixtures;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\attributes\Relation;
use dmstr\rest\sdk\attributes\ResourceType;
use dmstr\rest\sdk\entities\Entity;

#[ResourceType(resourceMethod: 'posts')]
class PostEntity extends Entity
{
    #[Property(readonly: true)]
    private int $id = 0;

    #[Property(mutable: true)]
    private string $title = '';

    #[Property(mutable: true, apiKey: 'category_id')]
    private int $categoryId = 0;

    #[Property(readonly: true)]
    private string $createdAt = '';

    #[Property(mutable: true, trackChanges: false)]
    private string $slug = '';

    #[Relation(
        entityClass: CommentEntity::class,
        expandKey: 'comments',
        multiple: true
    )]
    private array $comments = [];

    #[Relation(
        entityClass: AuthorEntity::class,
        expandKey: 'author'
    )]
    private ?AuthorEntity $author = null;

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

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getComments(): array
    {
        return $this->comments;
    }

    public function getAuthor(): ?AuthorEntity
    {
        return $this->author;
    }
}
