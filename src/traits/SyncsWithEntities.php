<?php

namespace dmstr\rest\sdk\traits;

use dmstr\rest\sdk\entities\Entity;
use ReflectionProperty;

/**
 * Trait for syncing Yii2 ActiveRecord attributes with REST SDK entities.
 *
 * Models using this trait must implement entityMap() returning a mapping of
 * Entity class names to [entityProperty => arAttribute] pairs.
 *
 * Usage:
 *   use SyncsWithEntities;
 *
 *   protected function entityMap(): array {
 *       return [
 *           PersonEntity::class => [
 *               'vorname' => 'first_name',
 *               'nachname' => 'surname',
 *           ],
 *       ];
 *   }
 */
trait SyncsWithEntities
{
    /**
     * Define the mapping between entity properties and ActiveRecord attributes.
     *
     * @return array<class-string<Entity>, array<string, string>> [EntityClass => [entityProp => arAttribute]]
     */
    abstract protected function entityMap(): array;

    /**
     * Populate AR attributes from one or more entities using getter methods.
     */
    public function populateFromEntities(Entity ...$entities): void
    {
        foreach ($entities as $entity) {
            $mapping = $this->resolveEntityMapping($entity);
            if ($mapping === null) {
                continue;
            }

            $ref = new \ReflectionClass($entity);
            foreach ($mapping as $entityProp => $arAttribute) {
                $prop = $ref->getProperty($entityProp);
                $this->$arAttribute = $prop->getValue($entity);
            }
        }
    }

    /**
     * Push AR attribute values to entities via setter methods, then upsert each.
     * Returns false on first failure; entity errors are available via getErrors().
     */
    public function syncToEntities(Entity ...$entities): bool
    {
        foreach ($entities as $entity) {
            $mapping = $this->resolveEntityMapping($entity);
            if ($mapping === null) {
                continue;
            }

            $ref = new \ReflectionClass($entity);
            foreach ($mapping as $entityProp => $arAttribute) {
                $prop = $ref->getProperty($entityProp);
                $prop->setValue($entity, $this->$arAttribute);
            }

            if (!$entity->upsert()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Translate entity error keys to AR attribute names and add them to this model.
     * Assumes the using class has an addError() method (e.g. yii\base\Model).
     */
    public function mergeEntityErrors(Entity $entity): void
    {
        $mapping = $this->resolveEntityMapping($entity);
        if ($mapping === null) {
            // Fall back: pass errors through with original keys
            foreach ($entity->getErrors() as $entityProp => $messages) {
                foreach ($messages as $message) {
                    $this->addError($entityProp, $message);
                }
            }
            return;
        }

        foreach ($entity->getErrors() as $entityProp => $messages) {
            $arAttribute = $mapping[$entityProp] ?? $entityProp;
            foreach ($messages as $message) {
                $this->addError($arAttribute, $message);
            }
        }
    }

    /**
     * Find the map entry matching the given entity via instanceof.
     *
     * @return array<string, string>|null [entityProp => arAttribute] or null if no match
     */
    private function resolveEntityMapping(Entity $entity): ?array
    {
        foreach ($this->entityMap() as $entityClass => $mapping) {
            if ($entity instanceof $entityClass) {
                return $mapping;
            }
        }
        return null;
    }
}
