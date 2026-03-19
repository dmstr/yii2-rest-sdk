<?php

namespace dmstr\rest\sdk\tests\fixtures;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\entities\Entity;

class EntityWithoutResource extends Entity
{
    #[Property(readonly: true)]
    private int $id = 0;
}
