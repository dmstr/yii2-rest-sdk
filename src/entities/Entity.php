<?php

namespace dmstr\rest\sdk\entities;

use dmstr\rest\sdk\attributes\ResourceType;
use dmstr\rest\sdk\exceptions\ValidationException;
use dmstr\rest\sdk\traits\AttributeMappedEntity;
use dmstr\rest\sdk\traits\HasErrors;
use dmstr\rest\sdk\interfaces\HttpClientInterface;
use dmstr\rest\sdk\resources\ReadonlyResource;
use JsonSerializable;
use ReflectionClass;
use RuntimeException;

abstract class Entity implements JsonSerializable
{
    use AttributeMappedEntity;
    use HasErrors;

    /**
     * Original API data (never modified)
     */
    private array $_data = [];

    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    /**
     * Set raw API data and auto-map to typed properties
     * Does not trigger autoDetectChanges so use with caution
     */
    public function setData(array $data): void
    {
        $this->_data = $data;
        $this->autoMapFromData($data);
    }

    /**
     * Get original API data
     */
    protected function getData(): array
    {
        return $this->_data;
    }

    /**
     * JSON serialization returns raw data (not typed properties)
     */
    public function jsonSerialize(): mixed
    {
        return $this->_data;
    }

    /**
     * Auto-detect changed attributes by comparing properties with original data
     */
    public function updateAttributes(): array
    {
        return $this->autoDetectChanges($this->_data);
    }

    /**
     * Generic update method - uses ResourceType attribute to determine resource
     * Returns false on validation errors (422), populating getErrors()
     */
    public function update(): bool
    {
        $this->clearErrors();
        $resource = $this->getResource();

        if (!method_exists($resource, 'update')) {
            throw new RuntimeException(
                'Resource ' . get_class($resource) . ' does not support update operations'
            );
        }

        try {
            return $resource->update($this);
        } catch (ValidationException $e) {
            $this->populateErrorsFromValidation($e);
            return false;
        }
    }

    /**
     * Generic create method - uses ResourceType attribute to determine resource.
     * Updates this entity with server-assigned data (e.g., id) after creation.
     * Returns false on validation errors (422), populating getErrors()
     */
    public function create(): bool
    {
        $this->clearErrors();
        $resource = $this->getResource();

        if (!method_exists($resource, 'create')) {
            throw new RuntimeException(
                'Resource ' . get_class($resource) . ' does not support create operations'
            );
        }

        try {
            $created = $resource->create($this);
            // Sync this entity with server-assigned data
            $this->setData($created->jsonSerialize());
            return true;
        } catch (ValidationException $e) {
            $this->populateErrorsFromValidation($e);
            return false;
        }
    }

    /**
     * Get the appropriate resource for this entity using ResourceType attribute
     */
    private function getResource(): ReadonlyResource
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(ResourceType::class);

        if (empty($attributes)) {
            throw new RuntimeException(
                'Entity ' . get_class($this) . ' must have ResourceType attribute'
            );
        }

        /** @var ResourceType $resourceType */
        $resourceType = $attributes[0]->newInstance();
        $method = $resourceType->resourceMethod;

        if (!method_exists($this->client, $method)) {
            throw new RuntimeException(
                "HttpClient does not have resource method: {$method}"
            );
        }

        return $this->client->$method();
    }

    /**
     * Get client instance (for nested entities to use)
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    /**
     * Get expand parameter keys for all relations
     * Returns comma-separated string for API expand parameter
     */
    public function getExpandParams(): string
    {
        $this->initializePropertyMap();
        $expandKeys = [];

        foreach ($this->_relationMap as $mapping) {
            $expandKeys[] = $mapping['attribute']->expandKey;
        }

        return implode(',', $expandKeys);
    }

    /**
     * Check if entity has a specific relation
     */
    public function hasRelation(string $propertyName): bool
    {
        $this->initializePropertyMap();
        return isset($this->_relationMap[$propertyName]);
    }

    /**
     * Get a related entity
     */
    public function getRelation(string $propertyName): mixed
    {
        if (!$this->hasRelation($propertyName)) {
            return null;
        }

        $property = $this->_relationMap[$propertyName]['reflection'];
        return $property->getValue($this);
    }

    /**
     * Inverted property map: API key → entity property name
     * Used by HasErrors to map API field names to entity properties
     */
    public function getApiKeyToPropertyMap(): array
    {
        $this->initializePropertyMap();
        $inverted = [];
        foreach ($this->_propertyMap as $propertyName => $mapping) {
            $inverted[$mapping['apiKey']] = $propertyName;
        }
        return $inverted;
    }

    /**
     * Map API validation errors to entity property names
     */
    public function populateErrorsFromValidation(ValidationException $e): void
    {
        $apiKeyToProperty = $this->getApiKeyToPropertyMap();

        foreach ($e->getErrorsByField() as $apiField => $messages) {
            $propertyName = $apiKeyToProperty[$apiField] ?? $apiField;
            foreach ($messages as $message) {
                $this->addError($propertyName, $message);
            }
        }
    }

    public function upsert(): bool
    {
        if (!isset($this->_data['id'])) {
            return $this->create();
        }
        return $this->update();
    }
}
