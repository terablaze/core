<?php

namespace TeraBlaze\Database\ORM\Exception;

use Exception;

use function implode;
use function sprintf;

/**
 * A MappingException indicates that something is wrong with the mapping setup.
 */
class MappingException extends Exception
{
    /**
     * @param string   $className
     * @param string[] $namespaces
     *
     * @return self
     */
    public static function classNotFoundInNamespaces($className, $namespaces): MappingException
    {
        return new self(sprintf(
            "The class '%s' was not found in the chain configured namespaces %s",
            $className,
            implode(', ', $namespaces)
        ));
    }

    /**
     * @return self
     */
    public static function pathRequired(): MappingException
    {
        return new self('Specifying the paths to your entities is required ' .
            'in the AnnotationDriver to retrieve all class names.');
    }

    /**
     * @param string|null $path
     *
     * @return self
     */
    public static function fileMappingDriversRequireConfiguredDirectoryPath($path = null): MappingException
    {
        if (! empty($path)) {
            $path = '[' . $path . ']';
        }

        return new self(sprintf(
            'File mapping drivers must have a valid directory path, ' .
            'however the given path %s seems to be incorrect!',
            $path
        ));
    }

    /**
     * @param string $entityName
     * @param string $fileName
     *
     * @return self
     */
    public static function mappingFileNotFound($entityName, $fileName): MappingException
    {
        return new self(sprintf(
            "No mapping file found named '%s' for class '%s'.",
            $fileName,
            $entityName
        ));
    }

    /**
     * @param string $entityName
     * @param string $fileName
     *
     * @return self
     */
    public static function invalidMappingFile($entityName, $fileName): MappingException
    {
        return new self(sprintf(
            "Invalid mapping file '%s' for class '%s'.",
            $fileName,
            $entityName
        ));
    }

    /**
     * @param string $className
     *
     * @return self
     */
    public static function nonExistingClass($className): MappingException
    {
        return new self(sprintf("Class '%s' does not exist", $className));
    }

    /**
     * @param string $entityName
     *
     * @return MappingException
     */
    public static function identifierRequired($entityName)
    {
        if (false !== ($parent = get_parent_class($entityName))) {
            return new self(sprintf(
                'No identifier/primary key specified for Entity "%s" sub class of "%s". Every Entity must have an identifier/primary key.',
                $entityName,
                $parent
            ));
        }

        return new self(sprintf(
            'No identifier/primary key specified for Entity "%s". Every Entity must have an identifier/primary key.',
            $entityName
        ));
    }

    /**
     * @param string $entityName
     * @param string $type
     *
     * @return MappingException
     */
    public static function invalidInheritanceType($entityName, $type)
    {
        return new self("The inheritance type '$type' specified for '$entityName' does not exist.");
    }

    /**
     * @return MappingException
     */
    public static function generatorNotAllowedWithCompositeId()
    {
        return new self("Id generators can't be used with a composite id.");
    }

    /**
     * @param string $entity
     *
     * @return MappingException
     */
    public static function missingPropertyName($entity)
    {
        return new self("The property or association mapping misses the 'propertyName' attribute in entity '$entity'.");
    }

    /**
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function missingTargetEntity($propertyName)
    {
        return new self("The association mapping '$propertyName' misses the 'targetEntity' attribute.");
    }

    /**
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function missingSourceEntity($propertyName)
    {
        return new self("The association mapping '$propertyName' misses the 'sourceEntity' attribute.");
    }

    /**
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function missingEmbeddedClass($propertyName)
    {
        return new self("The embed mapping '$propertyName' misses the 'class' attribute.");
    }

    /**
     * Exception for invalid property name override.
     *
     * @param string $className The entity's name.
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function invalidOverridePropertyName($className, $propertyName)
    {
        return new self("Invalid property override named '$propertyName' for class '$className'.");
    }

    /**
     * Exception for invalid property type override.
     *
     * @param string $className The entity's name.
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function invalidOverridePropertyType($className, $propertyName)
    {
        return new self("The column type of attribute '$propertyName' on class '$className' could not be changed.");
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function mappingNotFound($className, $propertyName)
    {
        return new self("No mapping found for property '$propertyName' on class '$className'.");
    }

    /**
     * @param string $className
     * @param string $queryName
     *
     * @return MappingException
     */
    public static function queryNotFound($className, $queryName)
    {
        return new self("No query found named '$queryName' on class '$className'.");
    }

    /**
     * @param string $className
     * @param string $resultName
     *
     * @return MappingException
     */
    public static function resultMappingNotFound($className, $resultName)
    {
        return new self("No result set mapping found named '$resultName' on class '$className'.");
    }

    /**
     * @param string $entity
     * @param string $queryName
     *
     * @return MappingException
     */
    public static function emptyQueryMapping($entity, $queryName)
    {
        return new self('Query named "' . $queryName . '" in "' . $entity . '" could not be empty.');
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function nameIsMandatoryForQueryMapping($className)
    {
        return new self("Query name on entity class '$className' is not defined.");
    }

    /**
     * @param string $entity
     * @param string $queryName
     *
     * @return MappingException
     */
    public static function missingQueryMapping($entity, $queryName)
    {
        return new self('Query named "' . $queryName . '" in "' . $entity . ' requires a result class or result set mapping.');
    }

    /**
     * @param string $entity
     * @param string $resultName
     *
     * @return MappingException
     */
    public static function missingResultSetMappingEntity($entity, $resultName)
    {
        return new self('Result set mapping named "' . $resultName . '" in "' . $entity . ' requires a entity class name.');
    }

    /**
     * @param string $entity
     * @param string $resultName
     *
     * @return MappingException
     */
    public static function missingResultSetMappingPropertyName($entity, $resultName)
    {
        return new self('Result set mapping named "' . $resultName . '" in "' . $entity . ' requires a property name.');
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function nameIsMandatoryForSqlResultSetMapping($className)
    {
        return new self("Result set mapping name on entity class '$className' is not defined.");
    }

    /**
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function oneToManyRequiresMappedBy($propertyName)
    {
        return new self("OneToMany mapping on property '$propertyName' requires the 'mappedBy' attribute.");
    }

    /**
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function joinTableRequired($propertyName)
    {
        return new self("The mapping of property '$propertyName' requires an the 'joinTable' attribute.");
    }

    /**
     * Called if a required option was not found but is required
     *
     * @param string $property          Which property cannot be processed?
     * @param string $expectedOption Which option is required
     * @param string $hint           Can optionally be used to supply a tip for common mistakes,
     *                               e.g. "Did you think of the plural s?"
     *
     * @return MappingException
     */
    public static function missingRequiredOption($property, $expectedOption, $hint = '')
    {
        $message = "The mapping of property '{$property}' is invalid: The option '{$expectedOption}' is required.";

        if (! empty($hint)) {
            $message .= ' (Hint: ' . $hint . ')';
        }

        return new self($message);
    }

    /**
     * Generic exception for invalid mappings.
     *
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function invalidMapping($propertyName)
    {
        return new self("The mapping of property '$propertyName' is invalid.");
    }

    /**
     * Exception for reflection exceptions - adds the entity name,
     * because there might be long classnames that will be shortened
     * within the stacktrace
     *
     * @param string               $entity            The entity's name
     * @param \ReflectionException $previousException
     *
     * @return MappingException
     */
    public static function reflectionFailure($entity, \ReflectionException $previousException)
    {
        return new self('An error occurred in ' . $entity, 0, $previousException);
    }

    /**
     * @param string $className
     * @param string $joinColumn
     *
     * @return MappingException
     */
    public static function joinColumnMustPointToMappedProperty($className, $joinColumn)
    {
        return new self('The column ' . $joinColumn . ' must be mapped to a property in class '
            . $className . ' since it is referenced by a join column of another class.');
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function classIsNotAValidEntityOrMappedSuperClass($className)
    {
        if (false !== ($parent = get_parent_class($className))) {
            return new self(sprintf(
                'Class "%s" sub class of "%s" is not a valid entity or mapped super class.',
                $className,
                $parent
            ));
        }

        return new self(sprintf(
            'Class "%s" is not a valid entity or mapped super class.',
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function propertyTypeIsRequired($className, $propertyName)
    {
        return new self("The attribute 'type' is required for the column description of property " . $className . "::\$" . $propertyName . ".");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function tableIdGeneratorNotImplemented($className)
    {
        return new self("TableIdGenerator is not yet implemented for use with class " . $className);
    }

    /**
     * @param string $entity    The entity's name.
     * @param string $propertyName The name of the property that was already declared.
     *
     * @return MappingException
     */
    public static function duplicatePropertyMapping($entity, $propertyName)
    {
        return new self('Property "' . $propertyName . '" in "' . $entity . '" was already declared, but it must be declared only once');
    }

    /**
     * @param string $entity
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function duplicateAssociationMapping($entity, $propertyName)
    {
        return new self('Property "' . $propertyName . '" in "' . $entity . '" was already declared, but it must be declared only once');
    }

    /**
     * @param string $entity
     * @param string $queryName
     *
     * @return MappingException
     */
    public static function duplicateQueryMapping($entity, $queryName)
    {
        return new self('Query named "' . $queryName . '" in "' . $entity . '" was already declared, but it must be declared only once');
    }

    /**
     * @param string $entity
     * @param string $resultName
     *
     * @return MappingException
     */
    public static function duplicateResultSetMapping($entity, $resultName)
    {
        return new self('Result set mapping named "' . $resultName . '" in "' . $entity . '" was already declared, but it must be declared only once');
    }

    /**
     * @param string $entity
     *
     * @return MappingException
     */
    public static function singleIdNotAllowedOnCompositePrimaryKey($entity)
    {
        return new self('Single id is not allowed on composite primary key in entity ' . $entity);
    }

    /**
     * @param string $entity
     *
     * @return MappingException
     */
    public static function noIdDefined($entity)
    {
        return new self('No ID defined for entity ' . $entity);
    }

    /**
     * @param string $entity
     * @param string $propertyName
     * @param string $unsupportedType
     *
     * @return MappingException
     */
    public static function unsupportedOptimisticLockingType($entity, $propertyName, $unsupportedType)
    {
        return new self('Locking type "' . $unsupportedType . '" (specified in "' . $entity . '", property "' . $propertyName . '") '
            . 'is not supported by Doctrine.');
    }

    /**
     * Returns an exception that indicates that a class used in a discriminator map does not exist.
     * An example would be an outdated (maybe renamed) classname.
     *
     * @param string $className   The class that could not be found
     * @param string $owningClass The class that declares the discriminator map.
     *
     * @return MappingException
     */
    public static function invalidClassInDiscriminatorMap($className, $owningClass)
    {
        return new self(
            "Entity class '$className' used in the discriminator map of class '$owningClass' " .
            "does not exist."
        );
    }

    /**
     * @param string $className
     * @param array  $entries
     * @param array  $map
     *
     * @return MappingException
     */
    public static function duplicateDiscriminatorEntry($className, array $entries, array $map)
    {
        return new self(
            "The entries " . implode(', ', $entries) . " in discriminator map of class '" . $className . "' is duplicated. " .
            "If the discriminator map is automatically generated you have to convert it to an explicit discriminator map now. " .
            "The entries of the current map are: @DiscriminatorMap({" . implode(', ', array_map(
                function ($a, $b) {
                    return "'$a': '$b'";
                },
                array_keys($map),
                array_values($map)
            )) . "})"
        );
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function missingDiscriminatorMap($className)
    {
        return new self("Entity class '$className' is using inheritance but no discriminator map was defined.");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function missingDiscriminatorColumn($className)
    {
        return new self("Entity class '$className' is using inheritance but no discriminator column was defined.");
    }

    /**
     * @param string $className
     * @param string $type
     *
     * @return MappingException
     */
    public static function invalidDiscriminatorColumnType($className, $type)
    {
        return new self("Discriminator column type on entity class '$className' is not allowed to be '$type'. 'string' or 'integer' type variables are suggested!");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function nameIsMandatoryForDiscriminatorColumns($className)
    {
        return new self("Discriminator column name on entity class '$className' is not defined.");
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function cannotVersionIdProperty($className, $propertyName)
    {
        return new self("Setting Id property '$propertyName' as versionable in entity class '$className' is not supported.");
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @param string $type
     *
     * @return MappingException
     */
    public static function sqlConversionNotAllowedForIdentifiers($className, $propertyName, $type)
    {
        return new self("It is not possible to set id property '$propertyName' to type '$type' in entity class '$className'. The type '$type' requires conversion SQL which is not allowed for identifiers.");
    }

    /**
     * @param string $className
     * @param string $columnName
     *
     * @return MappingException
     */
    public static function duplicateColumnName($className, $columnName)
    {
        return new self("Duplicate definition of column '" . $columnName . "' on entity '" . $className . "' in a property or discriminator column mapping.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function illegalToManyAssociationOnMappedSuperclass($className, $property)
    {
        return new self("It is illegal to put an inverse side one-to-many or many-to-many association on mapped superclass '" . $className . "#" . $property . "'.");
    }

    /**
     * @param string $className
     * @param string $targetEntity
     * @param string $targetProperty
     *
     * @return MappingException
     */
    public static function cannotMapCompositePrimaryKeyEntitiesAsForeignId($className, $targetEntity, $targetProperty)
    {
        return new self("It is not possible to map entity '" . $className . "' with a composite primary key " .
            "as part of the primary key of another entity '" . $targetEntity . "#" . $targetProperty . "'.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function noSingleAssociationJoinColumnFound($className, $property)
    {
        return new self("'$className#$property' is not an association with a single join column.");
    }

    /**
     * @param string $className
     * @param string $column
     *
     * @return MappingException
     */
    public static function noPropertyNameFoundForColumn($className, $column)
    {
        return new self("Cannot find a property on '$className' that is mapped to column '$column'. Either the " .
            "property does not exist or an association exists but it has multiple join columns.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function illegalOrphanRemovalOnIdentifierAssociation($className, $property)
    {
        return new self("The orphan removal option is not allowed on an association that is " .
            "part of the identifier in '$className#$property'.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function illegalOrphanRemoval($className, $property)
    {
        return new self("Orphan removal is only allowed on one-to-one and one-to-many " .
            "associations, but " . $className . "#" . $property . " is not.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function illegalInverseIdentifierAssociation($className, $property)
    {
        return new self("An inverse association is not allowed to be identifier in '$className#$property'.");
    }

    /**
     * @param string $className
     * @param string $property
     *
     * @return MappingException
     */
    public static function illegalToManyIdentifierAssociation($className, $property)
    {
        return new self("Many-to-many or one-to-many associations are not allowed to be identifier in '$className#$property'.");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function noInheritanceOnMappedSuperClass($className)
    {
        return new self("It is not supported to define inheritance information on a mapped superclass '" . $className . "'.");
    }

    /**
     * @param string $className
     * @param string $rootClassName
     *
     * @return MappingException
     */
    public static function mappedClassNotPartOfDiscriminatorMap($className, $rootClassName)
    {
        return new self(
            "Entity '" . $className . "' has to be part of the discriminator map of '" . $rootClassName . "' " .
            "to be properly mapped in the inheritance hierarchy. Alternatively you can make '" . $className . "' an abstract class " .
            "to avoid this exception from occurring."
        );
    }

    /**
     * @param string $className
     * @param string $methodName
     *
     * @return MappingException
     */
    public static function lifecycleCallbackMethodNotFound($className, $methodName)
    {
        return new self("Entity '" . $className . "' has no method '" . $methodName . "' to be registered as lifecycle callback.");
    }

    /**
     * @param string $listenerName
     * @param string $className
     *
     * @return self
     */
    public static function entityListenerClassNotFound($listenerName, $className)
    {
        return new self(sprintf('Entity Listener "%s" declared on "%s" not found.', $listenerName, $className));
    }

    /**
     * @param string $listenerName
     * @param string $methodName
     * @param string $className
     *
     * @return self
     */
    public static function entityListenerMethodNotFound($listenerName, $methodName, $className)
    {
        return new self(sprintf('Entity Listener "%s" declared on "%s" has no method "%s".', $listenerName, $className, $methodName));
    }

    /**
     * @param string $listenerName
     * @param string $methodName
     * @param string $className
     *
     * @return self
     */
    public static function duplicateEntityListener($listenerName, $methodName, $className)
    {
        return new self(sprintf('Entity Listener "%s#%s()" in "%s" was already declared, but it must be declared only once.', $listenerName, $methodName, $className));
    }

    /**
     * @param string $className
     * @param string $annotation
     *
     * @return MappingException
     */
    public static function invalidFetchMode($className, $annotation)
    {
        return new self("Entity '" . $className . "' has a mapping with invalid fetch mode '" . $annotation . "'");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function compositeKeyAssignedIdGeneratorRequired($className)
    {
        return new self("Entity '" . $className . "' has a composite identifier but uses an ID generator other than manually assigning (Identity, Sequence). This is not supported.");
    }

    /**
     * @param string $targetEntity
     * @param string $sourceEntity
     * @param string $associationName
     *
     * @return MappingException
     */
    public static function invalidTargetEntityClass($targetEntity, $sourceEntity, $associationName)
    {
        return new self("The target-entity " . $targetEntity . " cannot be found in '" . $sourceEntity . "#" . $associationName . "'.");
    }

    /**
     * @param array  $cascades
     * @param string $className
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function invalidCascadeOption(array $cascades, $className, $propertyName)
    {
        $cascades = implode(", ", array_map(function ($e) {
            return "'" . $e . "'";
        }, $cascades));

        return new self(sprintf(
            "You have specified invalid cascade options for %s::$%s: %s; available options: 'remove', 'persist', 'refresh', 'merge', and 'detach'",
            $className,
            $propertyName,
            $cascades
        ));
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function missingSequenceName($className)
    {
        return new self(
            sprintf('Missing "sequenceName" attribute for sequence id generator definition on class "%s".', $className)
        );
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return MappingException
     */
    public static function infiniteEmbeddableNesting($className, $propertyName)
    {
        return new self(
            sprintf(
                'Infinite nesting detected for embedded property %s::%s. ' .
                'You cannot embed an embeddable from the same type inside an embeddable.',
                $className,
                $propertyName
            )
        );
    }
}
