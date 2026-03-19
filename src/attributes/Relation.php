<?php

namespace dmstr\rest\sdk\attributes;

use Attribute;

/**
 * Attribute to mark a property as a related entity that can be loaded via expand parameter
 *
 * @example Single relation:
 * #[Relation(
 *     entityClass: PersonEntity::class,
 *     expandKey: 'person'
 * )]
 * private ?PersonEntity $person = null;
 *
 * @example Multiple relations:
 * #[Relation(
 *     entityClass: PersonKontaktinformationEntity::class,
 *     expandKey: 'kontaktinformationen',
 *     multiple: true
 * )]
 * private array $kontaktinformationen = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Relation
{
    public function __construct(
        public readonly string $entityClass,
        public readonly string $expandKey,
        public readonly bool $multiple = false
    ) {
    }
}
