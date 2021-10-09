<?php

namespace TeraBlaze\Database\ORM;

use BadMethodCallException;
use Doctrine\Instantiator\Instantiator;
use Doctrine\Instantiator\InstantiatorInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use TeraBlaze\Database\ORM\Exception\MappingException;

use function array_key_exists;
use function explode;

/**
 * A <tt>ClassMetadata</tt> instance holds all the object-relational mapping metadata
 * of an entity and its associations.
 *
 * Once populated, ClassMetadata instances are usually cached in a serialized form.
 *
 * <b>IMPORTANT NOTE:</b>
 *
 * The properties of this class are only public for 2 reasons:
 * 1) To allow fast READ access.
 * 2) To drastically reduce the size of a serialized instance (private/protected members
 *    get the whole class name, namespace inclusive, prepended to every property in
 *    the serialized representation).
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @since 2.0
 */
class ClassMetadata implements ClassMetadataInterface
{
    /* The Id generator types. */
    /**
     * AUTO means the generator type will depend on what the used platform prefers.
     * Offers full portability.
     */
    public const GENERATOR_TYPE_AUTO = 1;

    /**
     * SEQUENCE means a separate sequence object will be used. Platforms that do
     * not have native sequence support may emulate it. Full portability is currently
     * not guaranteed.
     */
    public const GENERATOR_TYPE_SEQUENCE = 2;

    /**
     * TABLE means a separate table is used for id generation.
     * Offers full portability.
     */
    public const GENERATOR_TYPE_TABLE = 3;

    /**
     * IDENTITY means an identity column is used for id generation. The database
     * will fill in the id column on insertion. Platforms that do not support
     * native identity columns may emulate them. Full portability is currently
     * not guaranteed.
     */
    public const GENERATOR_TYPE_IDENTITY = 4;

    /**
     * NONE means the class does not have a generated id. That means the class
     * must have a natural, manually assigned id.
     */
    public const GENERATOR_TYPE_NONE = 5;

    /**
     * UUID means that a UUID/GUID expression is used for id generation. Full
     * portability is currently not guaranteed.
     */
    public const GENERATOR_TYPE_UUID = 6;

    /**
     * CUSTOM means that customer will use own ID generator that supposedly work
     */
    public const GENERATOR_TYPE_CUSTOM = 7;

    /**
     * DEFERRED_IMPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done for all entities that are in MANAGED state at commit-time.
     *
     * This is the default change tracking policy.
     */
    public const CHANGETRACKING_DEFERRED_IMPLICIT = 1;

    /**
     * DEFERRED_EXPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done only for entities that were explicitly saved (through persist() or a cascade).
     */
    public const CHANGETRACKING_DEFERRED_EXPLICIT = 2;

    /**
     * NOTIFY means that Doctrine relies on the entities sending out notifications
     * when their properties change. Such entity classes must implement
     * the <tt>NotifyPropertyChanged</tt> interface.
     */
    public const CHANGETRACKING_NOTIFY = 3;

    /**
     * Specifies that an association is to be fetched when it is first accessed.
     */
    public const FETCH_LAZY = 2;

    /**
     * Specifies that an association is to be fetched when the owner of the
     * association is fetched.
     */
    public const FETCH_EAGER = 3;

    /**
     * Specifies that an association is to be fetched lazy (on first access) and that
     * commands such as Collection#count, Collection#slice are issued directly against
     * the database if the collection is not yet initialized.
     */
    public const FETCH_EXTRA_LAZY = 4;

    /**
     * Identifies a one-to-one association.
     */
    public const ONE_TO_ONE = 1;

    /**
     * Identifies a many-to-one association.
     */
    public const MANY_TO_ONE = 2;

    /**
     * Identifies a one-to-many association.
     */
    public const ONE_TO_MANY = 4;

    /**
     * Identifies a many-to-many association.
     */
    public const MANY_TO_MANY = 8;

    /**
     * Combined bitmask for to-one (single-valued) associations.
     */
    public const TO_ONE = 3;

    /**
     * Combined bitmask for to-many (collection-valued) associations.
     */
    public const TO_MANY = 12;

    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    /**
     * READ-ONLY: The property names of all properties that are part of the identifier/primary key
     * of the mapped entity class.
     *
     * @var array
     */
    public $identifier = [];

    /**
     * READ-ONLY: The Id generator type used by the class.
     *
     * @var int
     */
    public $generatorType = self::GENERATOR_TYPE_NONE;

    /**
     * READ-ONLY: The property mappings of the class.
     * Keys are property names and values are mapping definitions.
     *
     * The mapping definition array has the following values:
     *
     * - <b>propertyName</b> (string)
     * The name of the property in the Entity.
     *
     * - <b>type</b> (string)
     * The type name of the mapped property. Can be one of Doctrine's mapping types
     * or a custom mapping type.
     *
     * - <b>columnName</b> (string, optional)
     * The column name. Optional. Defaults to the property name.
     *
     * - <b>length</b> (integer, optional)
     * The database length of the column. Optional. Default value taken from
     * the type.
     *
     * - <b>id</b> (boolean, optional)
     * Marks the property as the primary key of the entity. Multiple properties of an
     * entity can have the id attribute, forming a composite key.
     *
     * - <b>nullable</b> (boolean, optional)
     * Whether the column is nullable. Defaults to FALSE.
     *
     * - <b>columnDefinition</b> (string, optional, schema-only)
     * The SQL fragment that is used when generating the DDL for the column.
     *
     * - <b>precision</b> (integer, optional, schema-only)
     * The precision of a decimal column. Only valid if the column type is decimal.
     *
     * - <b>scale</b> (integer, optional, schema-only)
     * The scale of a decimal column. Only valid if the column type is decimal.
     *
     * - <b>'unique'</b> (string, optional, schema-only)
     * Whether a unique constraint should be generated for the column.
     *
     * @var array
     *
     * @psalm-var array<string, array{type: string, propertyName: string, columnName: string}>
     */
    public $propertyMappings = [];

    /**
     * READ-ONLY: An array of property names. Used to look up property names from column names.
     * Keys are column names and values are property names.
     *
     * @var array
     */
    public $propertyNames = [];

    /**
     * READ-ONLY: The primary table definition. The definition is an array with the
     * following entries:
     *
     * name => <tableName>
     * schema => <schemaName>
     * connection => <connectionName>
     * indexes => array
     * uniqueConstraints => array
     *
     * @var array
     */
    public $table;

    /**
     * READ-ONLY: The association mappings of this class.
     *
     * The mapping definition array supports the following keys:
     *
     * - <b>propertyName</b> (string)
     * The name of the property in the entity the association is mapped to.
     *
     * - <b>targetEntity</b> (string)
     * The class name of the target entity. If it is fully-qualified it is used as is.
     * If it is a simple, unqualified class name the namespace is assumed to be the same
     * as the namespace of the source entity.
     *
     * - <b>mappedBy</b> (string, required for bidirectional associations)
     * The name of the property that completes the bidirectional association on the owning side.
     * This key must be specified on the inverse side of a bidirectional association.
     *
     * - <b>inversedBy</b> (string, required for bidirectional associations)
     * The name of the property that completes the bidirectional association on the inverse side.
     * This key must be specified on the owning side of a bidirectional association.
     *
     * - <b>cascade</b> (array, optional)
     * The names of persistence operations to cascade on the association. The set of possible
     * values are: "persist", "remove", "detach", "merge", "refresh", "all" (implies all others).
     *
     * - <b>orderBy</b> (array, one-to-many/many-to-many only)
     * A map of property names (of the target entity) to sorting directions (ASC/DESC).
     * Example: array('priority' => 'desc')
     *
     * - <b>fetch</b> (integer, optional)
     * The fetching strategy to use for the association, usually defaults to FETCH_LAZY.
     * Possible values are: ClassMetadata::FETCH_EAGER, ClassMetadata::FETCH_LAZY.
     *
     * - <b>joinTable</b> (array, optional, many-to-many only)
     * Specification of the join table and its join columns (foreign keys).
     * Only valid for many-to-many mappings. Note that one-to-many associations can be mapped
     * through a join table by simply mapping the association as many-to-many with a unique
     * constraint on the join table.
     *
     * - <b>indexBy</b> (string, optional, to-many only)
     * Specification of a property on target-entity that is used to index the collection by.
     * This property HAS to be either the primary key or a unique column. Otherwise the collection
     * does not contain all the entities that are actually related.
     *
     * A join table definition has the following structure:
     * <pre>
     * array(
     *     'name' => <join table name>,
     *      'joinColumns' => array(<join column mapping from join table to source table>),
     *      'inverseJoinColumns' => array(<join column mapping from join table to target table>)
     * )
     * </pre>
     *
     * @var array
     */
    public $associationMappings = [];

    /**
     * READ-ONLY: Flag indicating whether the identifier/primary key of the class is composite.
     *
     * @var boolean
     */
    public $isIdentifierComposite = false;

    /**
     * READ-ONLY: Flag indicating whether the identifier/primary key contains at least one foreign key association.
     *
     * This flag is necessary because some code blocks require special treatment of this cases.
     *
     * @var boolean
     */
    public $containsForeignIdentifier = false;

    /**
     * READ-ONLY: The definition of the sequence generator of this class. Only used for the
     * SEQUENCE generation strategy.
     *
     * The definition has the following structure:
     * <code>
     * array(
     *     'sequenceName' => 'name',
     *     'allocationSize' => 20,
     *     'initialValue' => 1
     * )
     * </code>
     *
     * @var array
     */
    public $tableGeneratorDefinition;

    /**
     * READ-ONLY: The policy used for change-tracking on entities of this class.
     *
     * @var integer
     */
    public $changeTrackingPolicy = self::CHANGETRACKING_DEFERRED_IMPLICIT;

    /**
     * The ReflectionClass instance of the mapped class.
     *
     * @var ReflectionClass
     */
    public $reflClass;

    /**
     * Is this entity marked as "read-only"?
     *
     * That means it is never considered for change-tracking in the UnitOfWork. It is a very helpful performance
     * optimization for entities that are immutable, either in your domain or through the relation database
     * (coming from a view, or a history table for example).
     *
     * @var bool
     */
    public $isReadOnly = false;

    /**
     * NamingStrategy determining the default column and table names.
     *
     * @var NamingStrategyInterface
     */
    protected $namingStrategy;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var ReflectionProperty[]|null[]
     */
    public $reflProperties = [];

    /**
     * @var InstantiatorInterface|null
     */
    private $instantiator;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param string              $entityName     The name of the entity class the new instance is used for.
     * @param NamingStrategyInterface|null $namingStrategy
     */
    public function __construct(string $entityName, NamingStrategyInterface $namingStrategy = null)
    {
        $this->name = $entityName;
        $this->namingStrategy = $namingStrategy ?: new DefaultNamingStrategy();
        $this->instantiator   = new Instantiator();
        $this->reflClass = new ReflectionClass($entityName);
    }

    /**
     * Gets the ReflectionProperties of the mapped class.
     *
     * @return ReflectionProperty[]|null[] An array of ReflectionProperty instances.
     *
     * @psalm-return array<ReflectionProperty|null>
     */
    public function getReflectionProperties()
    {
        return $this->reflProperties;
    }

    /**
     * Gets a ReflectionProperty for a specific property of the mapped class.
     *
     * @param string $name
     *
     * @return ReflectionProperty
     */
    public function getReflectionProperty($name)
    {
        return $this->reflProperties[$name];
    }

    /**
     * Gets the ReflectionProperty for the single identifier property.
     *
     * @return ReflectionProperty
     *
     * @throws BadMethodCallException If the class has a composite identifier.
     */
    public function getSingleIdReflectionProperty()
    {
        if ($this->isIdentifierComposite) {
            throw new BadMethodCallException("Class " . $this->name . " has a composite identifier.");
        }

        return $this->reflProperties[$this->identifier[0]];
    }

    /**
     * Extracts the identifier values of an entity of this class.
     *
     * For composite identifiers, the identifier values are returned as an array
     * with the same order as the property order in {@link identifier}.
     *
     * @param object $entity
     *
     * @return array
     */
    public function getIdentifierValues($entity)
    {
        if ($this->isIdentifierComposite) {
            $id = [];

            foreach ($this->identifier as $idProperty) {
                $value = $this->reflProperties[$idProperty]->getValue($entity);

                if (null !== $value) {
                    $id[$idProperty] = $value;
                }
            }

            return $id;
        }

        $id = $this->identifier[0];
        $value = $this->reflProperties[$id]->getValue($entity);

        if (null === $value) {
            return [];
        }

        return [$id => $value];
    }

    /**
     * Populates the entity identifier of an entity.
     *
     * @param object $entity
     * @param array  $id
     *
     * @return void
     */
    public function assignIdentifier($entity, array $id)
    {
        foreach ($id as $idProperty => $idValue) {
            $this->reflProperties[$idProperty]->setValue($entity, $idValue);
        }
    }

    /**
     * Sets the specified property to the specified value on the given entity.
     *
     * @param object $entity
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function setPropertyValue($entity, $property, $value)
    {
        $this->reflProperties[$property]->setValue($entity, $value);
    }

    /**
     * Gets the specified property's value off the given entity.
     *
     * @param object $entity
     * @param string $property
     *
     * @return mixed
     */
    public function getPropertyValue($entity, $property)
    {
        return $this->reflProperties[$property]->getValue($entity);
    }

    /**
     * Creates a string representation of this instance.
     *
     * @return string The string representation of this instance.
     *
     * @todo Construct meaningful string representation.
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * Determines which properties get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     * That means any metadata properties that are not set or empty or simply have
     * their default value are NOT serialized.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     *      - reflClass (ReflectionClass)
     *      - reflProperties (ReflectionProperty array)
     *
     * @return string[] The names of all the properties that should be serialized.
     */
    public function __sleep()
    {
        // This metadata is always serialized/cached.
        $serialized = [
            'associationMappings',
            'propertyMappings',
            'propertyNames',
            'identifier',
            'name',
            'table',
        ];

        // The rest of the metadata is only serialized if necessary.
        if ($this->changeTrackingPolicy != self::CHANGETRACKING_DEFERRED_IMPLICIT) {
            $serialized[] = 'changeTrackingPolicy';
        }

        if ($this->generatorType != self::GENERATOR_TYPE_NONE) {
            $serialized[] = 'generatorType';
            if ($this->generatorType == self::GENERATOR_TYPE_SEQUENCE) {
                $serialized[] = 'sequenceGeneratorDefinition';
            }
        }

        if ($this->containsForeignIdentifier) {
            $serialized[] = 'containsForeignIdentifier';
        }

        if ($this->isReadOnly) {
            $serialized[] = 'isReadOnly';
        }

        return $serialized;
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @return object
     */
    public function newInstance()
    {
        return $this->instantiator->instantiate($this->name);
    }

    /**
     * Validates Identifier.
     *
     * @return void
     *
     * @throws MappingException
     */
    public function validateIdentifier()
    {
        // Verify & complete identifier mapping
        if (! $this->identifier) {
            throw MappingException::identifierRequired($this->name);
        }

        if ($this->usesIdGenerator() && $this->isIdentifierComposite) {
            throw MappingException::compositeKeyAssignedIdGeneratorRequired($this->name);
        }
    }

    /**
     * Validates association targets actually exist.
     *
     * @return void
     *
     * @throws MappingException
     */
    public function validateAssociations()
    {
        foreach ($this->associationMappings as $mapping) {
            if (
                ! class_exists($mapping['targetEntity'])
                && ! interface_exists($mapping['targetEntity'])
                && ! trait_exists($mapping['targetEntity'])
            ) {
                throw MappingException
                    ::invalidTargetEntityClass($mapping['targetEntity'], $this->name, $mapping['propertyName']);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getReflectionClass()
    {
        return $this->reflClass;
    }

    /**
     * Sets the change tracking policy used by this class.
     *
     * @param integer $policy
     *
     * @return void
     */
    public function setChangeTrackingPolicy($policy)
    {
        $this->changeTrackingPolicy = $policy;
    }

    /**
     * Whether the change tracking policy of this class is "deferred explicit".
     *
     * @return boolean
     */
    public function isChangeTrackingDeferredExplicit()
    {
        return self::CHANGETRACKING_DEFERRED_EXPLICIT === $this->changeTrackingPolicy;
    }

    /**
     * Whether the change tracking policy of this class is "deferred implicit".
     *
     * @return boolean
     */
    public function isChangeTrackingDeferredImplicit()
    {
        return self::CHANGETRACKING_DEFERRED_IMPLICIT === $this->changeTrackingPolicy;
    }

    /**
     * Whether the change tracking policy of this class is "notify".
     *
     * @return boolean
     */
    public function isChangeTrackingNotify()
    {
        return self::CHANGETRACKING_NOTIFY === $this->changeTrackingPolicy;
    }

    /**
     * Checks whether a property is part of the identifier/primary key property(s).
     *
     * @param string $propertyName The property name.
     *
     * @return boolean TRUE if the property is part of the table identifier/primary key property(s),
     *                 FALSE otherwise.
     */
    public function isIdentifier($propertyName)
    {
        if (! $this->identifier) {
            return false;
        }

        if (! $this->isIdentifierComposite) {
            return $propertyName === $this->identifier[0];
        }

        return in_array($propertyName, $this->identifier, true);
    }

    /**
     * Checks if the property is unique.
     *
     * @param string $propertyName The property name.
     *
     * @return boolean TRUE if the property is unique, FALSE otherwise.
     */
    public function isUniqueProperty($propertyName)
    {
        $mapping = $this->getPropertyMapping($propertyName);

        return false !== $mapping && isset($mapping['unique']) && $mapping['unique'];
    }

    /**
     * Checks if the property is not null.
     *
     * @param string $propertyName The property name.
     *
     * @return boolean TRUE if the property is not null, FALSE otherwise.
     */
    public function isNullable($propertyName)
    {
        $mapping = $this->getPropertyMapping($propertyName);

        return false !== $mapping && isset($mapping['nullable']) && $mapping['nullable'];
    }

    /**
     * Gets a column name for a property name.
     * If the column name for the property cannot be found, the given property name
     * is returned.
     *
     * @param string $propertyName The property name.
     *
     * @return string The column name.
     */
    public function getColumnName($propertyName)
    {
        return $this->propertyMappings[$propertyName]['columnName'] ?? $propertyName;
    }

    /**
     * Gets the mapping of a (regular) property that holds some data but not a
     * reference to another object.
     *
     * @param string $propertyName The property name.
     *
     * @return array The property mapping.
     *
     * @throws MappingException
     */
    public function getPropertyMapping($propertyName)
    {
        if (! isset($this->propertyMappings[$propertyName])) {
            throw MappingException::mappingNotFound($this->name, $propertyName);
        }

        return $this->propertyMappings[$propertyName];
    }

    public function getPropertyOptions($propertyName)
    {
        $propertyMapping = $this->getPropertyMapping($propertyName);
        return $propertyMapping["options"] ?? [];
    }

    /**
     * Gets the mapping of an association.
     *
     * @param string $propertyName The property name that represents the association in
     *                          the object model.
     *
     * @return array The mapping.
     *
     * @throws MappingException
     * @see ClassMetadata::$associationMappings
     *
     */
    public function getAssociationMapping($propertyName)
    {
        if (! isset($this->associationMappings[$propertyName])) {
            throw MappingException::mappingNotFound($this->name, $propertyName);
        }

        return $this->associationMappings[$propertyName];
    }

    /**
     * Gets all association mappings of the class.
     *
     * @return array
     */
    public function getAssociationMappings()
    {
        return $this->associationMappings;
    }

    /**
     * Gets the property name for a column name.
     * If no property name can be found the column name is returned.
     *
     * @param string $columnName The column name.
     *
     * @return string The column alias.
     */
    public function getpropertyName($columnName)
    {
        return $this->propertyNames[$columnName] ?? $columnName;
    }

    /**
     * Validates & completes the given property mapping.
     *
     * @param array $mapping The property mapping to validate & complete.
     *
     * @return void
     *
     * @throws MappingException
     */
    protected function _validateAndCompletePropertyMapping(array &$mapping)
    {
        // Check mandatory properties
        if (! isset($mapping['propertyName']) || !$mapping['propertyName']) {
            throw MappingException::missingPropertyName($this->name);
        }

        if (! isset($mapping['type'])) {
            // Default to string
            $mapping['type'] = 'string';
        }

        // Complete propertyName and columnName mapping
        if (! isset($mapping['columnName'])) {
            $mapping['columnName'] = $this->namingStrategy->propertyToColumnName($mapping['propertyName'], $this->name);
        }

        if ('`' === $mapping['columnName'][0]) {
            $mapping['columnName']  = trim($mapping['columnName'], '`');
            $mapping['quoted']      = true;
        }

        $this->propertyNames[$mapping['columnName']] = $mapping['propertyName'];

        // Complete id mapping
        if (isset($mapping['id']) && true === $mapping['id']) {
            if (! in_array($mapping['propertyName'], $this->identifier)) {
                $this->identifier[] = $mapping['propertyName'];
            }

            // Check for composite key
            if (! $this->isIdentifierComposite && count($this->identifier) > 1) {
                $this->isIdentifierComposite = true;
            }
        }
    }

    /**
     * Validates & completes the basic mapping information that is common to all
     * association mappings (one-to-one, many-ot-one, one-to-many, many-to-many).
     *
     * @param array $mapping The mapping.
     *
     * @return mixed[] The updated mapping.
     *
     * @throws MappingException If something is wrong with the mapping.
     *
     * @psalm-return array{
     *                   mappedBy: mixed,
     *                   inversedBy: mixed,
     *                   isOwningSide: bool,
     *                   sourceEntity: string,
     *                   targetEntity: string,
     *                   propertyName: mixed,
     *                   fetch: mixed,
     *                   cascade: array<array-key,string>,
     *                   isCascadeRemove: bool,
     *                   isCascadePersist: bool,
     *                   isCascadeRefresh: bool,
     *                   isCascadeMerge: bool,
     *                   isCascadeDetach: bool
     *               }
     */
    protected function _validateAndCompleteAssociationMapping(array $mapping)
    {
        if (! isset($mapping['mappedBy'])) {
            $mapping['mappedBy'] = null;
        }

        if (! isset($mapping['inversedBy'])) {
            $mapping['inversedBy'] = null;
        }

        $mapping['isOwningSide'] = true; // assume owning side until we hit mappedBy

        if (empty($mapping['indexBy'])) {
            unset($mapping['indexBy']);
        }

        // If targetEntity is unqualified, assume it is in the same namespace as
        // the sourceEntity.
        $mapping['sourceEntity'] = $this->name;

        if (isset($mapping['targetEntity'])) {
            $mapping['targetEntity'] = $this->fullyQualifiedClassName($mapping['targetEntity']);
            $mapping['targetEntity'] = ltrim($mapping['targetEntity'], '\\');
        }

        if (
            ($mapping['type'] & self::MANY_TO_ONE) > 0 &&
            isset($mapping['orphanRemoval']) && $mapping['orphanRemoval']
        ) {
            throw MappingException::illegalOrphanRemoval($this->name, $mapping['propertyName']);
        }

        // Complete id mapping
        if (isset($mapping['id']) && true === $mapping['id']) {
            if (isset($mapping['orphanRemoval']) && $mapping['orphanRemoval']) {
                throw MappingException
                    ::illegalOrphanRemovalOnIdentifierAssociation($this->name, $mapping['propertyName']);
            }

            if (! in_array($mapping['propertyName'], $this->identifier)) {
                if (isset($mapping['joinColumns']) && count($mapping['joinColumns']) >= 2) {
                    throw MappingException::cannotMapCompositePrimaryKeyEntitiesAsForeignId(
                        $mapping['targetEntity'],
                        $this->name,
                        $mapping['propertyName']
                    );
                }

                $this->identifier[] = $mapping['propertyName'];
                $this->containsForeignIdentifier = true;
            }

            // Check for composite key
            if (! $this->isIdentifierComposite && count($this->identifier) > 1) {
                $this->isIdentifierComposite = true;
            }
        }

        // Mandatory attributes for both sides
        // Mandatory: propertyName, targetEntity
        if (! isset($mapping['propertyName']) || !$mapping['propertyName']) {
            throw MappingException::missingPropertyName($this->name);
        }

        if (! isset($mapping['targetEntity'])) {
            throw MappingException::missingTargetEntity($mapping['propertyName']);
        }

        // Mandatory and optional attributes for either side
        if (! $mapping['mappedBy']) {
            if (isset($mapping['joinTable']) && $mapping['joinTable']) {
                if (isset($mapping['joinTable']['name']) && $mapping['joinTable']['name'][0] === '`') {
                    $mapping['joinTable']['name']   = trim($mapping['joinTable']['name'], '`');
                    $mapping['joinTable']['quoted'] = true;
                }
            }
        } else {
            $mapping['isOwningSide'] = false;
        }

        if (isset($mapping['id']) && true === $mapping['id'] && $mapping['type'] & self::TO_MANY) {
            throw MappingException::illegalToManyIdentifierAssociation($this->name, $mapping['propertyName']);
        }

        // Fetch mode. Default fetch mode to LAZY, if not set.
        if (! isset($mapping['fetch'])) {
            $mapping['fetch'] = self::FETCH_LAZY;
        }

        // Cascades
        $cascades = isset($mapping['cascade']) ? array_map('strtolower', $mapping['cascade']) : [];

        $allCascades = ['remove', 'persist', 'refresh', 'merge', 'detach'];
        if (in_array('all', $cascades)) {
            $cascades = $allCascades;
        } elseif (count($cascades) !== count(array_intersect($cascades, $allCascades))) {
            throw MappingException::invalidCascadeOption(
                array_diff($cascades, $allCascades),
                $this->name,
                $mapping['propertyName']
            );
        }

        $mapping['cascade'] = $cascades;
        $mapping['isCascadeRemove']  = in_array('remove', $cascades);
        $mapping['isCascadePersist'] = in_array('persist', $cascades);
        $mapping['isCascadeRefresh'] = in_array('refresh', $cascades);
        $mapping['isCascadeMerge']   = in_array('merge', $cascades);
        $mapping['isCascadeDetach']  = in_array('detach', $cascades);

        return $mapping;
    }

    /**
     * Validates & completes a one-to-one association mapping.
     *
     * @param array $mapping The mapping to validate & complete.
     *
     * @return mixed[] The validated & completed mapping.
     *
     * @throws RuntimeException
     * @throws MappingException
     *
     * @psalm-return array{isOwningSide: mixed, orphanRemoval: bool, isCascadeRemove: bool}
     */
    protected function _validateAndCompleteOneToOneMapping(array $mapping)
    {
        $mapping = $this->_validateAndCompleteAssociationMapping($mapping);

        if (isset($mapping['joinColumns']) && $mapping['joinColumns']) {
            $mapping['isOwningSide'] = true;
        }

        if ($mapping['isOwningSide']) {
            if (empty($mapping['joinColumns'])) {
                // Apply default join column
                $mapping['joinColumns'] = [
                    [
                        'name' => $this->namingStrategy->joinColumnName($mapping['propertyName'], $this->name),
                        'referencedColumnName' => $this->namingStrategy->referenceColumnName()
                    ]
                ];
            }

            $uniqueConstraintColumns = [];

            foreach ($mapping['joinColumns'] as &$joinColumn) {
                if ($mapping['type'] === self::ONE_TO_ONE) {
                    if (count($mapping['joinColumns']) === 1) {
                        if (empty($mapping['id'])) {
                            $joinColumn['unique'] = true;
                        }
                    } else {
                        $uniqueConstraintColumns[] = $joinColumn['name'];
                    }
                }

                if (empty($joinColumn['name'])) {
                    $joinColumn['name'] = $this->namingStrategy->joinColumnName($mapping['propertyName'], $this->name);
                }

                if (empty($joinColumn['referencedColumnName'])) {
                    $joinColumn['referencedColumnName'] = $this->namingStrategy->referenceColumnName();
                }

                if ($joinColumn['name'][0] === '`') {
                    $joinColumn['name']   = trim($joinColumn['name'], '`');
                    $joinColumn['quoted'] = true;
                }

                if ($joinColumn['referencedColumnName'][0] === '`') {
                    $joinColumn['referencedColumnName'] = trim($joinColumn['referencedColumnName'], '`');
                    $joinColumn['quoted']               = true;
                }

                $mapping['sourceToTargetKeyColumns'][$joinColumn['name']] = $joinColumn['referencedColumnName'];
                $mapping['joinColumnpropertyNames'][$joinColumn['name']] =
                    $joinColumn['propertyName'] ??
                    $joinColumn['name'];
            }

            if ($uniqueConstraintColumns) {
                if (! $this->table) {
                    throw new RuntimeException(
                        "ClassMetadataInfo::setTable() has to be called before defining a one to one relationship."
                    );
                }

                $this->table['uniqueConstraints'][$mapping['propertyName'] . "_uniq"] = [
                    'columns' => $uniqueConstraintColumns
                ];
            }

            $mapping['targetToSourceKeyColumns'] = array_flip($mapping['sourceToTargetKeyColumns']);
        }

        $mapping['orphanRemoval']   = isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'];
        $mapping['isCascadeRemove'] = $mapping['orphanRemoval'] || $mapping['isCascadeRemove'];

        if ($mapping['orphanRemoval']) {
            unset($mapping['unique']);
        }

        if (isset($mapping['id']) && $mapping['id'] === true && !$mapping['isOwningSide']) {
            throw MappingException::illegalInverseIdentifierAssociation($this->name, $mapping['propertyName']);
        }

        return $mapping;
    }

    /**
     * Validates & completes a one-to-many association mapping.
     *
     * @param array $mapping The mapping to validate and complete.
     *
     * @return mixed[] The validated and completed mapping.
     *
     * @throws MappingException
     * @throws InvalidArgumentException
     *
     * @psalm-return array{
     *                   mappedBy: mixed,
     *                   inversedBy: mixed,
     *                   isOwningSide: bool,
     *                   sourceEntity: string,
     *                   targetEntity: string,
     *                   propertyName: mixed,
     *                   fetch: int|mixed,
     *                   cascade: array<array-key,string>,
     *                   isCascadeRemove: bool,
     *                   isCascadePersist: bool,
     *                   isCascadeRefresh: bool,
     *                   isCascadeMerge: bool,
     *                   isCascadeDetach: bool,
     *                   orphanRemoval: bool
     *               }
     */
    protected function _validateAndCompleteOneToManyMapping(array $mapping)
    {
        $mapping = $this->_validateAndCompleteAssociationMapping($mapping);

        // OneToMany-side MUST be inverse (must have mappedBy)
        if (! isset($mapping['mappedBy'])) {
            throw MappingException::oneToManyRequiresMappedBy($mapping['propertyName']);
        }

        $mapping['orphanRemoval']   = isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'];
        $mapping['isCascadeRemove'] = $mapping['orphanRemoval'] || $mapping['isCascadeRemove'];

        $this->assertMappingOrderBy($mapping);

        return $mapping;
    }

    /**
     * Validates & completes a many-to-many association mapping.
     *
     * @param array $mapping The mapping to validate & complete.
     *
     * @return mixed[] The validated & completed mapping.
     *
     * @throws \InvalidArgumentException
     *
     * @psalm-return array{isOwningSide: mixed, orphanRemoval: bool}
     */
    protected function _validateAndCompleteManyToManyMapping(array $mapping)
    {
        $mapping = $this->_validateAndCompleteAssociationMapping($mapping);

        if ($mapping['isOwningSide']) {
            // owning side MUST have a join table
            if (! isset($mapping['joinTable']['name'])) {
                $mapping['joinTable']['name'] =
                    $this->namingStrategy->joinTableName(
                        $mapping['sourceEntity'],
                        $mapping['targetEntity'],
                        $mapping['propertyName']
                    );
            }

            $selfReferencingEntityWithoutJoinColumns = $mapping['sourceEntity'] == $mapping['targetEntity']
                && (! (isset($mapping['joinTable']['joinColumns']) ||
                    isset($mapping['joinTable']['inverseJoinColumns'])));

            if (! isset($mapping['joinTable']['joinColumns'])) {
                $mapping['joinTable']['joinColumns'] = [
                    [
                        'name' => $this->namingStrategy
                            ->joinKeyColumnName(
                                $mapping['sourceEntity'],
                                $selfReferencingEntityWithoutJoinColumns ? 'source' : null
                            ),
                        'referencedColumnName' => $this->namingStrategy->referenceColumnName(),
                        'onDelete' => 'CASCADE'
                    ]
                ];
            }

            if (! isset($mapping['joinTable']['inverseJoinColumns'])) {
                $mapping['joinTable']['inverseJoinColumns'] = [
                    [
                        'name' =>
                            $this->namingStrategy
                                ->joinKeyColumnName(
                                    $mapping['targetEntity'],
                                    $selfReferencingEntityWithoutJoinColumns ? 'target' : null
                                ),
                        'referencedColumnName' => $this->namingStrategy->referenceColumnName(),
                        'onDelete' => 'CASCADE'
                    ]
                ];
            }

            $mapping['joinTableColumns'] = [];

            foreach ($mapping['joinTable']['joinColumns'] as &$joinColumn) {
                if (empty($joinColumn['name'])) {
                    $joinColumn['name'] =
                        $this->namingStrategy
                            ->joinKeyColumnName($mapping['sourceEntity'], $joinColumn['referencedColumnName']);
                }

                if (empty($joinColumn['referencedColumnName'])) {
                    $joinColumn['referencedColumnName'] = $this->namingStrategy->referenceColumnName();
                }

                if ($joinColumn['name'][0] === '`') {
                    $joinColumn['name']   = trim($joinColumn['name'], '`');
                    $joinColumn['quoted'] = true;
                }

                if ($joinColumn['referencedColumnName'][0] === '`') {
                    $joinColumn['referencedColumnName'] = trim($joinColumn['referencedColumnName'], '`');
                    $joinColumn['quoted']               = true;
                }

                if (isset($joinColumn['onDelete']) && strtolower($joinColumn['onDelete']) == 'cascade') {
                    $mapping['isOnDeleteCascade'] = true;
                }

                $mapping['relationToSourceKeyColumns'][$joinColumn['name']] = $joinColumn['referencedColumnName'];
                $mapping['joinTableColumns'][] = $joinColumn['name'];
            }

            foreach ($mapping['joinTable']['inverseJoinColumns'] as &$inverseJoinColumn) {
                if (empty($inverseJoinColumn['name'])) {
                    $inverseJoinColumn['name'] =
                        $this->namingStrategy
                            ->joinKeyColumnName($mapping['targetEntity'], $inverseJoinColumn['referencedColumnName']);
                }

                if (empty($inverseJoinColumn['referencedColumnName'])) {
                    $inverseJoinColumn['referencedColumnName'] = $this->namingStrategy->referenceColumnName();
                }

                if ($inverseJoinColumn['name'][0] === '`') {
                    $inverseJoinColumn['name']   = trim($inverseJoinColumn['name'], '`');
                    $inverseJoinColumn['quoted'] = true;
                }

                if ($inverseJoinColumn['referencedColumnName'][0] === '`') {
                    $inverseJoinColumn['referencedColumnName']  = trim($inverseJoinColumn['referencedColumnName'], '`');
                    $inverseJoinColumn['quoted']                = true;
                }

                if (isset($inverseJoinColumn['onDelete']) && strtolower($inverseJoinColumn['onDelete']) == 'cascade') {
                    $mapping['isOnDeleteCascade'] = true;
                }

                $mapping['relationToTargetKeyColumns'][$inverseJoinColumn['name']] =
                    $inverseJoinColumn['referencedColumnName'];
                $mapping['joinTableColumns'][] = $inverseJoinColumn['name'];
            }
        }

        $mapping['orphanRemoval'] = isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'];

        $this->assertMappingOrderBy($mapping);

        return $mapping;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierPropertyNames()
    {
        return $this->identifier;
    }

    /**
     * Gets the name of the single id property. Note that this only works on
     * entity classes that have a single-property pk.
     *
     * @return string
     *
     * @throws MappingException If the class doesn't have an identifier or it has a composite primary key.
     */
    public function getSingleIdentifierPropertyName()
    {
        if ($this->isIdentifierComposite) {
            throw MappingException::singleIdNotAllowedOnCompositePrimaryKey($this->name);
        }

        if (! isset($this->identifier[0])) {
            throw MappingException::noIdDefined($this->name);
        }

        return $this->identifier[0];
    }

    /**
     * Gets the column name of the single id column. Note that this only works on
     * entity classes that have a single-property pk.
     *
     * @return string
     *
     * @throws MappingException If the class doesn't have an identifier or it has a composite primary key.
     */
    public function getSingleIdentifierColumnName()
    {
        return $this->getColumnName($this->getSingleIdentifierPropertyName());
    }

    /**
     * INTERNAL:
     * Sets the mapped identifier/primary key properties of this class.
     * Mainly used by the ClassMetadataFactory to assign inherited identifiers.
     *
     * @param array $identifier
     *
     * @return void
     */
    public function setIdentifier(array $identifier)
    {
        $this->identifier = $identifier;
        $this->isIdentifierComposite = (count($this->identifier) > 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function hasProperty($propertyName)
    {
        return isset($this->propertyMappings[$propertyName]) || isset($this->embeddedClasses[$propertyName]);
    }

    /**
     * Gets an array containing all the column names.
     *
     * @param array|null $propertyNames
     *
     * @return mixed[]
     *
     * @psalm-return list<string>
     */
    public function getColumnNames(array $propertyNames = null)
    {
        if (null === $propertyNames) {
            return array_keys($this->propertyNames);
        }

        return array_values(array_map([$this, 'getColumnName'], $propertyNames));
    }

    /**
     * Returns an array with all the identifier column names.
     *
     * @return array
     */
    public function getIdentifierColumnNames()
    {
        $columnNames = [];

        foreach ($this->identifier as $idProperty) {
            if (isset($this->propertyMappings[$idProperty])) {
                $columnNames[] = $this->propertyMappings[$idProperty]['columnName'];

                continue;
            }

            // Association defined as Id property
            $joinColumns      = $this->associationMappings[$idProperty]['joinColumns'];
            $assocColumnNames = array_map(function ($joinColumn) {
                return $joinColumn['name'];
            }, $joinColumns);

            $columnNames = array_merge($columnNames, $assocColumnNames);
        }

        return $columnNames;
    }

    /**
     * Sets the type of Id generator to use for the mapped class.
     *
     * @param int $generatorType
     *
     * @return void
     */
    public function setIdGeneratorType($generatorType)
    {
        $this->generatorType = $generatorType;
    }

    /**
     * Checks whether the mapped class uses an Id generator.
     *
     * @return boolean TRUE if the mapped class uses an Id generator, FALSE otherwise.
     */
    public function usesIdGenerator()
    {
        return $this->generatorType != self::GENERATOR_TYPE_NONE;
    }

    /**
     * Checks whether the class uses an identity column for the Id generation.
     *
     * @return boolean TRUE if the class uses the IDENTITY generator, FALSE otherwise.
     */
    public function isIdGeneratorIdentity()
    {
        return $this->generatorType == self::GENERATOR_TYPE_IDENTITY;
    }

    /**
     * Checks whether the class uses a sequence for id generation.
     *
     * @return boolean TRUE if the class uses the SEQUENCE generator, FALSE otherwise.
     */
    public function isIdGeneratorSequence()
    {
        return $this->generatorType == self::GENERATOR_TYPE_SEQUENCE;
    }

    /**
     * Checks whether the class uses a table for id generation.
     *
     * @return boolean TRUE if the class uses the TABLE generator, FALSE otherwise.
     */
    public function isIdGeneratorTable()
    {
        return $this->generatorType == self::GENERATOR_TYPE_TABLE;
    }

    /**
     * Checks whether the class has a natural identifier/pk (which means it does
     * not use any Id generator.
     *
     * @return boolean
     */
    public function isIdentifierNatural()
    {
        return $this->generatorType == self::GENERATOR_TYPE_NONE;
    }

    /**
     * Checks whether the class use a UUID for id generation.
     *
     * @return boolean
     */
    public function isIdentifierUuid()
    {
        return $this->generatorType == self::GENERATOR_TYPE_UUID;
    }

    /**
     * Gets the type of a property.
     *
     * @param string $propertyName
     *
     * @return string|null
     */
    public function getPropertyType($propertyName)
    {
        return isset($this->propertyMappings[$propertyName])
            ? $this->propertyMappings[$propertyName]['type']
            : null;
    }

    /**
     * Gets the name of the primary table.
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->table['name'];
    }

    /**
     * Gets primary table's schema name.
     *
     * @return string|null
     */
    public function getSchemaName()
    {
        return isset($this->table['schema']) ? $this->table['schema'] : null;
    }

    /**
     * Gets primary table's connection name.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return isset($this->table['connection']) ? $this->table['connection'] : null;
    }

    /**
     * Gets the table name to use for temporary identifier tables of this class.
     *
     * @return string
     */
    public function getTemporaryIdTableName()
    {
        // replace dots with underscores because PostgreSQL creates temporary tables in a special schema
        return str_replace('.', '_', $this->getTableName() . '_id_tmp');
    }

    /**
     * Sets the primary table definition. The provided array supports the
     * following structure:
     *
     * name => <tableName> (optional, defaults to class name)
     * indexes => array of indexes (optional)
     * uniqueConstraints => array of constraints (optional)
     *
     * If a key is omitted, the current value is kept.
     *
     * @param array $table The table description.
     *
     * @return void
     */
    public function setPrimaryTable(array $table)
    {
        if (isset($table['name'])) {
            // Split schema and table name from a table name like "myschema.mytable"
            if (strpos($table['name'], '.') !== false) {
                [$this->table['schema'], $table['name']] = explode('.', $table['name'], 2);
            }

            if ($table['name'][0] === '`') {
                $table['name']          = trim($table['name'], '`');
                $this->table['quoted']  = true;
            }

            $this->table['name'] = $table['name'];
        }

        if (isset($table['quoted'])) {
            $this->table['quoted'] = $table['quoted'];
        }

        if (isset($table['schema'])) {
            $this->table['schema'] = $table['schema'];
        }

        if (isset($table['connection'])) {
            $this->table['connection'] = $table['connection'];
        }

        if (isset($table['indexes'])) {
            $this->table['indexes'] = $table['indexes'];
        }

        if (isset($table['uniqueConstraints'])) {
            $this->table['uniqueConstraints'] = $table['uniqueConstraints'];
        }

        if (isset($table['options'])) {
            $this->table['options'] = $table['options'];
        }
    }

    /**
     * Adds a mapped property to the class.
     *
     * @param array $mapping The property mapping.
     *
     * @return void
     *
     * @throws MappingException
     */
    public function mapProperty(array $mapping)
    {
        $this->_validateAndCompletePropertyMapping($mapping);
        $this->assertPropertyNotMapped($mapping['propertyName']);

        $this->propertyMappings[$mapping['propertyName']] = $mapping;
    }

    /**
     * Adds a one-to-one mapping.
     *
     * @param array $mapping The mapping.
     *
     * @return void
     */
    public function mapOneToOne(array $mapping)
    {
        $mapping['type'] = self::ONE_TO_ONE;

        $mapping = $this->_validateAndCompleteOneToOneMapping($mapping);

        $this->_storeAssociationMapping($mapping);
    }

    /**
     * Adds a one-to-many mapping.
     *
     * @param array $mapping The mapping.
     *
     * @return void
     */
    public function mapOneToMany(array $mapping)
    {
        $mapping['type'] = self::ONE_TO_MANY;

        $mapping = $this->_validateAndCompleteOneToManyMapping($mapping);

        $this->_storeAssociationMapping($mapping);
    }

    /**
     * Adds a many-to-one mapping.
     *
     * @param array $mapping The mapping.
     *
     * @return void
     */
    public function mapManyToOne(array $mapping)
    {
        $mapping['type'] = self::MANY_TO_ONE;

        // A many-to-one mapping is essentially a one-one backreference
        $mapping = $this->_validateAndCompleteOneToOneMapping($mapping);

        $this->_storeAssociationMapping($mapping);
    }

    /**
     * Adds a many-to-many mapping.
     *
     * @param array $mapping The mapping.
     *
     * @return void
     */
    public function mapManyToMany(array $mapping)
    {
        $mapping['type'] = self::MANY_TO_MANY;

        $mapping = $this->_validateAndCompleteManyToManyMapping($mapping);

        $this->_storeAssociationMapping($mapping);
    }

    /**
     * Stores the association mapping.
     *
     * @param array $assocMapping
     *
     * @return void
     *
     * @throws MappingException
     */
    protected function _storeAssociationMapping(array $assocMapping)
    {
        $sourcePropertyName = $assocMapping['propertyName'];

        $this->assertPropertyNotMapped($sourcePropertyName);

        $this->associationMappings[$sourcePropertyName] = $assocMapping;
    }

    /**
     * Registers a custom repository class for the entity class.
     *
     * @param string $repositoryClassName The class name of the custom mapper.
     *
     * @return void
     *
     * @psalm-param class-string $repositoryClassName
     */
    public function setCustomRepositoryClass($repositoryClassName)
    {
        $this->customRepositoryClassName = $this->fullyQualifiedClassName($repositoryClassName);
    }

    /**
     * {@inheritDoc}
     */
    public function hasAssociation($propertyName)
    {
        return isset($this->associationMappings[$propertyName]);
    }

    /**
     * {@inheritDoc}
     */
    public function isSingleValuedAssociation($propertyName)
    {
        return isset($this->associationMappings[$propertyName])
            && ($this->associationMappings[$propertyName]['type'] & self::TO_ONE);
    }

    /**
     * {@inheritDoc}
     */
    public function isCollectionValuedAssociation($propertyName)
    {
        return isset($this->associationMappings[$propertyName])
            && ! ($this->associationMappings[$propertyName]['type'] & self::TO_ONE);
    }

    /**
     * Is this an association that only has a single join column?
     *
     * @param string $propertyName
     *
     * @return bool
     */
    public function isAssociationWithSingleJoinColumn($propertyName)
    {
        return isset($this->associationMappings[$propertyName])
            && isset($this->associationMappings[$propertyName]['joinColumns'][0])
            && ! isset($this->associationMappings[$propertyName]['joinColumns'][1]);
    }

    /**
     * Returns the single association join column (if any).
     *
     * @param string $propertyName
     *
     * @return string
     *
     * @throws MappingException
     */
    public function getSingleAssociationJoinColumnName($propertyName)
    {
        if (! $this->isAssociationWithSingleJoinColumn($propertyName)) {
            throw MappingException::noSingleAssociationJoinColumnFound($this->name, $propertyName);
        }

        return $this->associationMappings[$propertyName]['joinColumns'][0]['name'];
    }

    /**
     * Returns the single association referenced join column name (if any).
     *
     * @param string $propertyName
     *
     * @return string
     *
     * @throws MappingException
     */
    public function getSingleAssociationReferencedJoinColumnName($propertyName)
    {
        if (! $this->isAssociationWithSingleJoinColumn($propertyName)) {
            throw MappingException::noSingleAssociationJoinColumnFound($this->name, $propertyName);
        }

        return $this->associationMappings[$propertyName]['joinColumns'][0]['referencedColumnName'];
    }

    /**
     * Used to retrieve a propertyName for either property or association from a given column.
     *
     * This method is used in foreign-key as primary-key contexts.
     *
     * @param string $columnName
     *
     * @return string
     *
     * @throws MappingException
     */
    public function getPropertyForColumn($columnName)
    {
        if (isset($this->propertyNames[$columnName])) {
            return $this->propertyNames[$columnName];
        }

        foreach ($this->associationMappings as $assocName => $mapping) {
            if (
                $this->isAssociationWithSingleJoinColumn($assocName) &&
                $this->associationMappings[$assocName]['joinColumns'][0]['name'] == $columnName
            ) {
                return $assocName;
            }
        }

        throw MappingException::noPropertyNameFoundForColumn($this->name, $columnName);
    }

    public function getAllMappings(): array
    {
        return array_merge($this->propertyMappings, $this->associationMappings);
    }

    /**
     * Sets definition.
     *
     * @param array $definition
     *
     * @return void
     */
    public function setCustomGeneratorDefinition(array $definition)
    {
        $this->customGeneratorDefinition = $definition;
    }

    /**
     * Sets the definition of the sequence ID generator for this class.
     *
     * The definition must have the following structure:
     * <code>
     * array(
     *     'sequenceName'   => 'name',
     *     'allocationSize' => 20,
     *     'initialValue'   => 1
     *     'quoted'         => 1
     * )
     * </code>
     *
     * @param array $definition
     *
     * @return void
     *
     * @throws MappingException
     */
    public function setSequenceGeneratorDefinition(array $definition)
    {
        if (! isset($definition['sequenceName']) || trim($definition['sequenceName']) === '') {
            throw MappingException::missingSequenceName($this->name);
        }

        if ($definition['sequenceName'][0] == '`') {
            $definition['sequenceName']   = trim($definition['sequenceName'], '`');
            $definition['quoted'] = true;
        }

        if (! isset($definition['allocationSize']) || trim($definition['allocationSize']) === '') {
            $definition['allocationSize'] = '1';
        }

        if (! isset($definition['initialValue']) || trim($definition['initialValue']) === '') {
            $definition['initialValue'] = '1';
        }

        $this->sequenceGeneratorDefinition = $definition;
    }

    /**
     * Marks this class as read only, no change tracking is applied to it.
     *
     * @return void
     */
    public function markReadOnly()
    {
        $this->isReadOnly = true;
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyNames()
    {
        return array_keys($this->propertyMappings);
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationNames()
    {
        return array_keys($this->associationMappings);
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     */
    public function getAssociationTargetClass($assocName)
    {
        if (! isset($this->associationMappings[$assocName])) {
            throw new InvalidArgumentException(
                "Association name expected, '" . $assocName . "' is not an association."
            );
        }

        return $this->associationMappings[$assocName]['targetEntity'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociationInverseSide($propertyName)
    {
        return isset($this->associationMappings[$propertyName])
            && ! $this->associationMappings[$propertyName]['isOwningSide'];
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationMappedByTargetProperty($propertyName)
    {
        return $this->associationMappings[$propertyName]['mappedBy'];
    }

    /**
     * @param string $targetClass
     *
     * @return array
     */
    public function getAssociationsByTargetClass($targetClass)
    {
        $relations = [];

        foreach ($this->associationMappings as $mapping) {
            if ($mapping['targetEntity'] == $targetClass) {
                $relations[$mapping['propertyName']] = $mapping;
            }
        }

        return $relations;
    }

    /**
     * @param  string|null $className
     *
     * @return string|null null if the input value is null
     *
     * @psalm-param ?class-string $className
     */
    public function fullyQualifiedClassName($className)
    {
        if (empty($className)) {
            return $className;
        }

        if ($className !== null && strpos($className, '\\') === false && $this->namespace) {
            return $this->namespace . '\\' . $className;
        }

        return $className;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getMetadataValue($name)
    {

        if (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * @param string $propertyName
     * @throws MappingException
     */
    private function assertPropertyNotMapped($propertyName)
    {
        if (
            isset($this->propertyMappings[$propertyName]) ||
            isset($this->associationMappings[$propertyName]) ||
            isset($this->embeddedClasses[$propertyName])
        ) {
            throw MappingException::duplicatePropertyMapping($this->name, $propertyName);
        }
    }

    /**
     * @param array $mapping
     */
    private function assertMappingOrderBy(array $mapping)
    {
        if (isset($mapping['orderBy']) && !is_array($mapping['orderBy'])) {
            throw new InvalidArgumentException(
                "'orderBy' is expected to be an array, not " . gettype($mapping['orderBy'])
            );
        }
    }
}
