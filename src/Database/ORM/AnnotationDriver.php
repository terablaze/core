<?php

namespace Terablaze\Database\ORM;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Terablaze\Database\ORM\Exception\MappingException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use ReflectionClass;
use RegexIterator;

use function array_merge;
use function array_unique;
use function get_class;
use function get_declared_classes;
use function in_array;
use function is_dir;
use function preg_match;
use function preg_quote;
use function realpath;
use function str_replace;
use function strpos;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 */
class AnnotationDriver
{
    /**
     * The annotation reader.
     *
     * @var Reader
     */
    protected $reader;

    /**
     * The paths where to look for mapping files.
     *
     * @var string[]
     */
    protected $paths = [];

    /**
     * The paths excluded from path where to look for mapping files.
     *
     * @var string[]
     */
    protected $excludePaths = [];

    /**
     * The file extension of mapping documents.
     *
     * @var string
     */
    protected $fileExtension = '.php';

    /**
     * Cache for AnnotationDriver#getAllClassNames().
     *
     * @var string[]|null
     */
    protected $classNames;

    /**
     * @var int[]
     * @psalm-var array<class-string, int>
     */
    protected $entityAnnotationClasses = [
        Mapping\Table::class => 1,
    ];

    /**
     * Initializes a new AnnotationDriver that uses the given AnnotationReader for reading
     * docblock annotations.
     *
     * @param Reader $reader The AnnotationReader to use, duck-typed.
     * @param string|string[]|null $paths One or multiple paths where mapping classes can be found.
     */
    public function __construct($reader, $paths = null)
    {
        $this->reader = $reader;
        if (!$paths) {
            return;
        }

        $this->addPaths((array)$paths);
    }

    /**
     * Appends lookup paths to metadata driver.
     *
     * @param string[] $paths
     *
     * @return void
     */
    public function addPaths(array $paths)
    {
        $this->paths = array_unique(array_merge($this->paths, $paths));
    }

    /**
     * Retrieves the defined metadata lookup paths.
     *
     * @return string[]
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Append exclude lookup paths to metadata driver.
     *
     * @param string[] $paths
     */
    public function addExcludePaths(array $paths)
    {
        $this->excludePaths = array_unique(array_merge($this->excludePaths, $paths));
    }

    /**
     * Retrieve the defined metadata lookup exclude paths.
     *
     * @return string[]
     */
    public function getExcludePaths()
    {
        return $this->excludePaths;
    }

    /**
     * Retrieve the current annotation reader
     *
     * @return Reader
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * Gets the file extension used to look for mapping files under.
     *
     * @return string
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * Sets the file extension used to look for mapping files under.
     *
     * @param string $fileExtension The file extension to set.
     *
     * @return void
     */
    public function setFileExtension($fileExtension)
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * Returns whether the class with the specified name is transient. Only non-transient
     * classes, that is entities and mapped superclasses, should have their metadata loaded.
     *
     * A class is non-transient if it is annotated with an annotation
     * from the {@see AnnotationDriver::entityAnnotationClasses}.
     *
     * @param string $className
     *
     * @return bool
     */
    public function isTransient($className)
    {
        $classAnnotations = $this->reader->getClassAnnotations(new ReflectionClass($className));

        foreach ($classAnnotations as $annot) {
            if (isset($this->entityAnnotationClasses[get_class($annot)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        if ($this->classNames !== null) {
            return $this->classNames;
        }

        if (!$this->paths) {
            throw MappingException::pathRequired();
        }

        $classes = [];
        $includedFiles = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath($path);
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+' . preg_quote($this->fileExtension) . '$/i',
                RecursiveRegexIterator::GET_MATCH
            );

            foreach ($iterator as $file) {
                $sourceFile = $file[0];

                if (!preg_match('(^phar:)i', $sourceFile)) {
                    $sourceFile = realpath($sourceFile);
                }

                foreach ($this->excludePaths as $excludePath) {
                    $exclude = str_replace('\\', '/', realpath($excludePath));
                    $current = str_replace('\\', '/', $sourceFile);

                    if (strpos($current, $exclude) !== false) {
                        continue 2;
                    }
                }

                require_once $sourceFile;

                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $rc = new ReflectionClass($className);
            $sourceFile = $rc->getFileName();
            if (!in_array($sourceFile, $includedFiles) || $this->isTransient($className)) {
                continue;
            }

            $classes[] = $className;
        }

        $this->classNames = $classes;

        return $classes;
    }

    public function loadMetadataForClass($className, ClassMetadata $metadata = null)
    {
        $class = $metadata->getReflectionClass();

        if (!$class) {
            // this happens when running annotation driver in combination with
            // static reflection services. This is not the nicest fix
            $class = new \ReflectionClass($metadata->name);
        }

        $classAnnotations = $this->reader->getClassAnnotations($class);

        if ($classAnnotations) {
            foreach ($classAnnotations as $key => $annot) {
                if (!is_numeric($key)) {
                    continue;
                }

                $classAnnotations[get_class($annot)] = $annot;
            }
        }

        // Evaluate Entity annotation
        if (!isset($classAnnotations[Mapping\Table::class])) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($className);
        } else {
            $tableAnnot = $classAnnotations[Mapping\Table::class];

            if ($tableAnnot->readOnly) {
                // TODO: Save readonly metadata
                $metadata->markReadOnly();
            }

            $primaryTable = [
                'name' => $tableAnnot->name ?? $class->getShortName(),
                'schema' => $tableAnnot->schema,
                'connection' => $tableAnnot->connection
            ];

            if ($tableAnnot->indexes !== null) {
                foreach ($tableAnnot->indexes as $indexAnnot) {
                    $index = ['columns' => $indexAnnot->columns];

                    if (!empty($indexAnnot->flags)) {
                        $index['flags'] = $indexAnnot->flags;
                    }

                    if (!empty($indexAnnot->options)) {
                        $index['options'] = $indexAnnot->options;
                    }

                    if (!empty($indexAnnot->name)) {
                        $primaryTable['indexes'][$indexAnnot->name] = $index;
                    } else {
                        $primaryTable['indexes'][] = $index;
                    }
                }
            }

            if ($tableAnnot->uniqueConstraints !== null) {
                foreach ($tableAnnot->uniqueConstraints as $uniqueConstraintAnnot) {
                    $uniqueConstraint = ['columns' => $uniqueConstraintAnnot->columns];

                    if (!empty($uniqueConstraintAnnot->options)) {
                        $uniqueConstraint['options'] = $uniqueConstraintAnnot->options;
                    }

                    if (!empty($uniqueConstraintAnnot->name)) {
                        $primaryTable['uniqueConstraints'][$uniqueConstraintAnnot->name] = $uniqueConstraint;
                    } else {
                        $primaryTable['uniqueConstraints'][] = $uniqueConstraint;
                    }
                }
            }

            if ($tableAnnot->options) {
                $primaryTable['options'] = $tableAnnot->options;
            }

            $metadata->setPrimaryTable($primaryTable);
        }

        // Evaluate annotations on properties
        foreach ($class->getProperties() as $property) {
            if ($property->isPrivate()) {
                continue;
            }

            $mapping = [];
            $mapping['propertyName'] = $property->getName();

            // Check for JoinColumn/JoinColumns annotations
            $joinColumns = [];

            if ($joinColumnAnnot = $this->reader->getPropertyAnnotation($property, Mapping\JoinColumn::class)) {
                $joinColumns[] = $this->joinColumnToArray($joinColumnAnnot);
            } elseif ($joinColumnsAnnot = $this->reader->getPropertyAnnotation($property, Mapping\JoinColumns::class)) {
                foreach ($joinColumnsAnnot->value as $joinColumn) {
                    $joinColumns[] = $this->joinColumnToArray($joinColumn);
                }
            }

            // Property can only be annotated with one of:
            // @Column, @OneToOne, @OneToMany, @ManyToOne, @ManyToMany
            if ($columnAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Column::class)) {
                if ($columnAnnot->type == null) {
                    throw MappingException::propertyTypeIsRequired($className, $property->getName());
                }

                $mapping = $this->columnToArray($property->getName(), $columnAnnot);

                if ($idAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Id::class)) {
                    $mapping['id'] = true;
                }

                if ($encrypt = $this->reader->getPropertyAnnotation($property, Mapping\Encrypt::class)) {
                    $mapping['encrypt'] = true;
                }

                if (
                    $generatedValueAnnot =
                        $this->reader->getPropertyAnnotation($property, Mapping\GeneratedValue::class)
                ) {
                    $metadata->setIdGeneratorType(
                        constant(
                            'Terablaze\Database\ORM\ClassMetadata::GENERATOR_TYPE_' . $generatedValueAnnot->strategy
                        )
                    );
                }
                $metadata->mapProperty($mapping);
            } elseif ($oneToOneAnnot = $this->reader->getPropertyAnnotation($property, Mapping\OneToOne::class)) {
                if ($idAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Id::class)) {
                    $mapping['id'] = true;
                }
                $mapping['targetEntity'] = $oneToOneAnnot->targetEntity;
                $mapping['joinColumns'] = $joinColumns;
                $mapping['mappedBy'] = $oneToOneAnnot->mappedBy;
                $mapping['inversedBy'] = $oneToOneAnnot->inversedBy;
                $mapping['cascade'] = $oneToOneAnnot->cascade;
                $mapping['orphanRemoval'] = $oneToOneAnnot->orphanRemoval;
                $mapping['fetch'] = $this->getFetchMode($className, $oneToOneAnnot->fetch);
                $metadata->mapOneToOne($mapping);
            } elseif ($oneToManyAnnot = $this->reader->getPropertyAnnotation($property, Mapping\OneToMany::class)) {
                $mapping['mappedBy'] = $oneToManyAnnot->mappedBy;
                $mapping['targetEntity'] = $oneToManyAnnot->targetEntity;
                $mapping['cascade'] = $oneToManyAnnot->cascade;
                $mapping['indexBy'] = $oneToManyAnnot->indexBy;
                $mapping['orphanRemoval'] = $oneToManyAnnot->orphanRemoval;
                $mapping['fetch'] = $this->getFetchMode($className, $oneToManyAnnot->fetch);

                if ($whereAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Where::class)) {
                    $mapping['where'] = $whereAnnot->value;
                }

                if ($orderByAnnot = $this->reader->getPropertyAnnotation($property, Mapping\OrderBy::class)) {
                    $mapping['orderBy'] = $orderByAnnot->value;
                }

                if ($limitAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Limit::class)) {
                    $mapping['limit'] = $limitAnnot->value;
                }

                if ($paginationAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Pagination::class)) {
                    $query = null;
                    switch ($paginationAnnot->source) {
                        case "request.post":
                            $query = request()->getPostParam($paginationAnnot->query, 1);
                            break;
                        case "cookie":
                            $query = request()->getCookieParam($paginationAnnot->query, 1);
                            break;
                        case "session":
                            $query = request()->getSession()->get($paginationAnnot->query, 1);
                            break;
                        case "request.get":
                        default:
                            $query = request()->getQueryParam($paginationAnnot->query, 1);
                            break;
                    }
                    $mapping['pagination'] = [
                        "limit" => $paginationAnnot->limit,
                        "type" => $paginationAnnot->type,
                        "query" => $query,
                    ];
                }

                $metadata->mapOneToMany($mapping);
            } elseif ($manyToOneAnnot = $this->reader->getPropertyAnnotation($property, Mapping\ManyToOne::class)) {
                if ($idAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Id::class)) {
                    $mapping['id'] = true;
                }

                $mapping['joinColumns'] = $joinColumns;
                $mapping['cascade'] = $manyToOneAnnot->cascade;
                $mapping['inversedBy'] = $manyToOneAnnot->inversedBy;
                $mapping['targetEntity'] = $manyToOneAnnot->targetEntity;
                $mapping['fetch'] = $this->getFetchMode($className, $manyToOneAnnot->fetch);
                $metadata->mapManyToOne($mapping);
            } elseif ($manyToManyAnnot = $this->reader->getPropertyAnnotation($property, Mapping\ManyToMany::class)) {
                $joinTable = [];

                if ($joinTableAnnot = $this->reader->getPropertyAnnotation($property, Mapping\JoinTable::class)) {
                    $joinTable = [
                        'name' => $joinTableAnnot->name,
                        'schema' => $joinTableAnnot->schema,
                        'connection' => $tableAnnot->connection
                    ];

                    foreach ($joinTableAnnot->joinColumns as $joinColumn) {
                        $joinTable['joinColumns'][] = $this->joinColumnToArray($joinColumn);
                    }

                    foreach ($joinTableAnnot->inverseJoinColumns as $joinColumn) {
                        $joinTable['inverseJoinColumns'][] = $this->joinColumnToArray($joinColumn);
                    }
                }

                $mapping['joinTable'] = $joinTable;
                $mapping['targetEntity'] = $manyToManyAnnot->targetEntity;
                $mapping['mappedBy'] = $manyToManyAnnot->mappedBy;
                $mapping['inversedBy'] = $manyToManyAnnot->inversedBy;
                $mapping['cascade'] = $manyToManyAnnot->cascade;
                $mapping['indexBy'] = $manyToManyAnnot->indexBy;
                $mapping['orphanRemoval'] = $manyToManyAnnot->orphanRemoval;
                $mapping['fetch'] = $this->getFetchMode($className, $manyToManyAnnot->fetch);

                if ($whereAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Where::class)) {
                    $mapping['where'] = $whereAnnot->value;
                }

                if ($orderByAnnot = $this->reader->getPropertyAnnotation($property, Mapping\OrderBy::class)) {
                    $mapping['orderBy'] = $orderByAnnot->value;
                }

                if ($limitAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Limit::class)) {
                    $mapping['limit'] = $limitAnnot->value;
                }

                if ($paginationAnnot = $this->reader->getPropertyAnnotation($property, Mapping\Pagination::class)) {
                    $query = null;
                    switch ($paginationAnnot->source) {
                        case "request.post":
                            $query = request()->getPostParam($paginationAnnot->query, 1);
                            break;
                        case "cookie":
                            $query = request()->getCookieParam($paginationAnnot->query, 1);
                            break;
                        case "session":
                            $query = request()->getSession()->get($paginationAnnot->query, 1);
                            break;
                        case "request.get":
                        default:
                            $query = request()->getQueryParam($paginationAnnot->query, 1);
                            break;
                    }
                    $mapping['pagination'] = [
                        "limit" => $paginationAnnot->limit,
                        "type" => $paginationAnnot->type,
                        "query" => $query,
                    ];
                }

                $metadata->mapManyToMany($mapping);
            }
        }
    }

    /**
     * Attempts to resolve the fetch mode.
     *
     * @param string $className The class name.
     * @param string $fetchMode The fetch mode.
     *
     * @return integer The fetch mode as defined in ClassMetadata.
     *
     * @throws MappingException If the fetch mode is not valid.
     */
    private function getFetchMode($className, $fetchMode)
    {
        if (!defined('Terablaze\Database\ORM\ClassMetadata::FETCH_' . $fetchMode)) {
            throw MappingException::invalidFetchMode($className, $fetchMode);
        }

        return constant('Terablaze\Database\ORM\ClassMetadata::FETCH_' . $fetchMode);
    }

    /**
     * Parse the given JoinColumn as array
     *
     * @param Mapping\JoinColumn $joinColumn
     *
     * @return mixed[]
     *
     * @psalm-return array{
     *                   name: string,
     *                   unique: bool,
     *                   nullable: bool,
     *                   onDelete: mixed,
     *                   columnDefinition: string,
     *                   referencedColumnName: string
     *               }
     */
    private function joinColumnToArray(Mapping\JoinColumn $joinColumn)
    {
        return [
            'name' => $joinColumn->name,
            'unique' => $joinColumn->unique,
            'nullable' => $joinColumn->nullable,
            'onDelete' => $joinColumn->onDelete,
            'columnDefinition' => $joinColumn->columnDefinition,
            'referencedColumnName' => $joinColumn->referencedColumnName,
        ];
    }

    /**
     * Parse the given Column as array
     *
     * @param string $propertyName
     * @param Mapping\Column $column
     *
     * @return mixed[]
     *
     * @psalm-return array{
     *                   propertyName: string,
     *                   type: mixed,
     *                   scale: int,
     *                   length: int,
     *                   unique: bool,
     *                   nullable: bool,
     *                   precision: int,
     *                   options?: mixed[],
     *                   columnName?: string,
     *                   columnDefinition?: string
     *               }
     */
    private function columnToArray($propertyName, Mapping\Column $column)
    {
        $mapping = [
            'propertyName' => $propertyName,
            'type' => $column->type,
            'scale' => $column->scale,
            'length' => $column->length,
            'unique' => $column->unique,
            'nullable' => $column->nullable,
            'precision' => $column->precision
        ];

        if ($column->options) {
            $mapping['options'] = $column->options;
        }

        if (isset($column->name)) {
            $mapping['columnName'] = $column->name;
        }

        if (isset($column->columnDefinition)) {
            $mapping['columnDefinition'] = $column->columnDefinition;
        }

        return $mapping;
    }

    /**
     * Factory method for the Annotation Driver.
     *
     * @param array|string $paths
     * @param AnnotationReader|null $reader
     *
     * @return AnnotationDriver
     */
    public static function create($paths = [], AnnotationReader $reader = null): AnnotationDriver
    {
        if ($reader == null) {
            $reader = new AnnotationReader();
        }

        return new self($reader, $paths);
    }
}
