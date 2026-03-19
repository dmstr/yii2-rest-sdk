<?php

namespace dmstr\rest\sdk\tests;

use dmstr\rest\sdk\attributes\Property;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PropertyAttributeTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $prop = new Property();

        self::assertFalse($prop->mutable);
        self::assertFalse($prop->readonly);
        self::assertNull($prop->apiKey);
        self::assertTrue($prop->trackChanges);
    }

    public function testMutableProperty(): void
    {
        $prop = new Property(mutable: true);

        self::assertTrue($prop->isMutable());
    }

    public function testReadonlyProperty(): void
    {
        $prop = new Property(readonly: true);

        self::assertFalse($prop->isMutable());
    }

    public function testMutableAndReadonlyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Property(mutable: true, readonly: true);
    }

    public function testExplicitApiKey(): void
    {
        $prop = new Property(apiKey: 'custom_key');

        self::assertSame('custom_key', $prop->apiKey);
    }

    public function testTrackChangesDisabled(): void
    {
        $prop = new Property(mutable: true, trackChanges: false);

        self::assertTrue($prop->isMutable());
        self::assertFalse($prop->trackChanges);
    }
}
