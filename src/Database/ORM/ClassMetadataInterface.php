<?php

namespace Terablaze\Database\ORM;

use ReflectionClass;

/**
 * Contract for a Doctrine persistence layer ClassMetadata class to implement.
 */
interface ClassMetadataInterface
{
    /**
     * Gets the fully-qualified class name of this persistent class.
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the mapped identifier property name.
     *
     * The returned structure is an array of the identifier property names.
     *
     * @return mixed[]
     */
    public function getIdentifier();

    /**
     * Gets the ReflectionClass instance for this mapped class.
     *
     * @return ReflectionClass
     */
    public function getReflectionClass();

    /**
     * Checks if the given property name is a mapped identifier for this class.
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function isIdentifier($propertyName);

    /**
     * Checks if the given property is a mapped property for this class.
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function hasProperty($propertyName);

    /**
     * Checks if the given property is a mapped association for this class.
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function hasAssociation($propertyName);

    /**
     * Checks if the given property is a mapped single valued association for this class.
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function isSingleValuedAssociation($propertyName);

    /**
     * Checks if the given property is a mapped collection valued association for this class.
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function isCollectionValuedAssociation($propertyName);

    /**
     * A numerically indexed list of property names of this persistent class.
     *
     * This array includes identifier properties if present on this class.
     *
     * @return string[]
     */
    public function getPropertyNames();

    /**
     * Returns an array of identifier property names numerically indexed.
     *
     * @return string[]
     */
    public function getIdentifierPropertyNames();

    /**
     * Returns a numerically indexed list of association names of this persistent class.
     *
     * This array includes identifier associations if present on this class.
     *
     * @return string[]
     */
    public function getAssociationNames();

    /**
     * Returns a type name of this property.
     *
     * This type names can be implementation specific but should at least include the php types:
     * integer, string, boolean, float/double, datetime.
     *
     * @param string $propertyName
     *
     * @return string
     */
    public function getPropertyType($propertyName);

    /**
     * Returns the target class name of the given association.
     *
     * @param string $assocName
     *
     * @return string
     */
    public function getAssociationTargetClass($assocName);

    /**
     * Checks if the association is the inverse side of a bidirectional association.
     *
     * @param string $assocName
     *
     * @return bool
     */
    public function isAssociationInverseSide($assocName);

    /**
     * Returns the target property of the owning side of the association.
     *
     * @param string $assocName
     *
     * @return string
     */
    public function getAssociationMappedByTargetProperty($assocName);

    /**
     * Returns the identifier of this object as an array with property name as key.
     *
     * Has to return an empty array if no identifier isset.
     *
     * @param object $object
     *
     * @return mixed[]
     */
    public function getIdentifierValues($object);
}
