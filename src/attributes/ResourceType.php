<?php

namespace dmstr\rest\sdk\attributes;

use Attribute;

/**
 * Class-level attribute to specify which resource handles this entity
 *
 * @example
 * #[ResourceType(resourceMethod: 'benutzer')]
 * class BenutzerEntity extends Entity
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ResourceType
{
    public function __construct(
        public readonly string $resourceMethod
    ) {
    }
}
