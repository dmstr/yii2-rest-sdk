<?php

namespace dmstr\rest\sdk\traits;

use dmstr\rest\sdk\attributes\Property;
use dmstr\rest\sdk\attributes\Relation;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Trait providing automatic property mapping and change tracking via PHP 8 attributes
 */
trait AttributeMappedEntity
{
    /**
     * Cached property mapping configuration
     */
    private array $_propertyMap = [];

    /**
     * Cached relation mapping configuration
     */
    private array $_relationMap = [];

    /**
     * Flag to prevent re-initialization
     */
    private bool $_propertyMapInitialized = false;

    /**
     * Initialize property and relation mapping from attributes
     * Cached per instance for performance
     */
    private function initializePropertyMap(): void
    {
        if ($this->_propertyMapInitialized) {
            return;
        }

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            // Check for Property attribute
            $propertyAttributes = $property->getAttributes(Property::class);
            if (!empty($propertyAttributes)) {
                /** @var Property $propAttr */
                $propAttr = $propertyAttributes[0]->newInstance();

                // Determine API key: either explicit or convert property name (camelCase -> snake_case)
                $apiKey = $propAttr->apiKey ?? $this->camelToSnake($property->getName());

                $this->_propertyMap[$property->getName()] = [
                    'attribute' => $propAttr,
                    'apiKey' => $apiKey,
                    'reflection' => $property
                ];
            }

            // Check for Relation attribute
            $relationAttributes = $property->getAttributes(Relation::class);
            if (!empty($relationAttributes)) {
                /** @var Relation $relAttr */
                $relAttr = $relationAttributes[0]->newInstance();

                $this->_relationMap[$property->getName()] = [
                    'attribute' => $relAttr,
                    'reflection' => $property
                ];
            }
        }

        $this->_propertyMapInitialized = true;
    }

    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Auto-map API data to typed properties based on #[Property] attributes
     */
    protected function autoMapFromData(array $data): void
    {
        $this->initializePropertyMap();

        // Map regular properties
        foreach ($this->_propertyMap as $propertyName => $mapping) {
            $apiKey = $mapping['apiKey'];

            if (!array_key_exists($apiKey, $data)) {
                continue;
            }

            /** @var ReflectionProperty $property */
            $property = $mapping['reflection'];
            // Type coercion based on property type
            $value = $this->coerceType($data[$apiKey], $property);
            $property->setValue($this, $value);
        }

        // Map relations if present in data
        $this->autoMapRelations($data);
    }

    /**
     * Auto-map related entities from API data
     */
    protected function autoMapRelations(array $data): void
    {
        foreach ($this->_relationMap as $propertyName => $mapping) {
            /** @var Relation $relAttr */
            $relAttr = $mapping['attribute'];
            $expandKey = $relAttr->expandKey;

            if (!array_key_exists($expandKey, $data)) {
                continue;
            }

            /** @var ReflectionProperty $property */
            $property = $mapping['reflection'];

            $relationData = $data[$expandKey];

            if ($relAttr->multiple) {
                // Handle array of entities
                $entities = [];
                if (is_array($relationData)) {
                    foreach ($relationData as $itemData) {
                        $entityClass = $relAttr->entityClass;
                        $entity = new $entityClass($this->client);
                        $entity->setData($itemData);
                        $entities[] = $entity;
                    }
                }
                $property->setValue($this, $entities);
            } else {
                // Handle single entity
                if (!empty($relationData) && is_array($relationData)) {
                    $entityClass = $relAttr->entityClass;
                    $entity = new $entityClass($this->client);
                    $entity->setData($relationData);
                    $property->setValue($this, $entity);
                }
            }
        }
    }

    /**
     * Generate array of changed attributes for PATCH by comparing current values with original data
     */
    protected function autoDetectChanges(array $originalData): array
    {
        $this->initializePropertyMap();
        $changes = [];

        foreach ($this->_propertyMap as $propertyName => $mapping) {
            /** @var Property $attribute */
            $attribute = $mapping['attribute'];
            $apiKey = $mapping['apiKey'];

            // Skip readonly and non-trackable properties
            if (!$attribute->isMutable() || !$attribute->trackChanges) {
                continue;
            }

            /** @var ReflectionProperty $property */
            $property = $mapping['reflection'];
            $currentValue = $property->getValue($this);

            $originalValue = $originalData[$apiKey] ?? null;

            // Compare values (handles type coercion)
            if ($this->valuesAreDifferent($currentValue, $originalValue)) {
                $changes[$apiKey] = $currentValue;
            }
        }

        return $changes;
    }

    /**
     * Type coercion helper - converts API values to match property types
     */
    private function coerceType(mixed $value, ReflectionProperty $property): mixed
    {
        $type = $property->getType();

        if ($type === null) {
            return $value;
        }

        if ($value === null) {
            return $type->allowsNull() ? null : $property->getDefaultValue();
        }

        // Handle union types by using the first non-null type
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => (array) $value,
            default => $value
        };
    }

    /**
     * Compare values considering type coercion
     * Uses loose comparison to handle "1" vs 1 scenarios
     */
    private function valuesAreDifferent(mixed $current, mixed $original): bool
    {
        if ($current === null && $original === null) {
            return false;
        }
        if ($current === null || $original === null) {
            return true;
        }
        // Cast original to match current's type since API types may differ (e.g., "1" vs 1)
        return $current !== match (true) {
            is_bool($current) => (bool) $original,
            is_int($current) => (int) $original,
            is_float($current) => (float) $original,
            is_string($current) => (string) $original,
            default => $original,
        };
    }
}
