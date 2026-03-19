<?php

namespace dmstr\rest\sdk\tests\fixtures;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\entities\Entity;

class AuthorEntity extends Entity
{
    #[Property(readonly: true)]
    private int $id = 0;

    #[Property(readonly: true)]
    private string $name = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
