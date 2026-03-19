<?php

namespace dmstr\rest\sdk\tests\fixtures;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\entities\Entity;

class CommentEntity extends Entity
{
    #[Property(readonly: true)]
    private int $id = 0;

    #[Property(mutable: true)]
    private string $body = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}
