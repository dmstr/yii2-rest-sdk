<?php

namespace dmstr\rest\sdk\attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Attribute to define property mapping between entity properties and API fields
 *
 * @example
 * #[Property(readonly: true)]
 * private int $id;
 *
 * #[Property(mutable: true, apiKey: 'herkunft_id')]
 * private int $herkunftId;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Property
{
    public function __construct(
        public readonly bool $mutable = false,
        public readonly bool $readonly = false,
        public readonly ?string $apiKey = null,
        public readonly bool $trackChanges = true
    ) {
        // Validation: can't be both mutable and readonly
        if ($this->mutable && $this->readonly) {
            throw new InvalidArgumentException('Property cannot be both mutable and readonly');
        }
    }

    /**
     * Check if property is mutable (can be changed and sent to API)
     */
    public function isMutable(): bool
    {
        return $this->mutable && !$this->readonly;
    }
}
