<?php

namespace Terablaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Table implements Annotation
{
    /**
     * @var string
     */
    public $repositoryClass;

    /**
     * @var boolean
     */
    public $readOnly = false;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $schema;

    /**
     * @var string
     */
    public $connection = "default";

    /**
     * @var array<\Terablaze\Database\ORM\Mapping\Index>
     */
    public $indexes;

    /**
     * @var array<\Terablaze\Database\ORM\Mapping\UniqueConstraint>
     */
    public $uniqueConstraints;

    /**
     * @var array
     */
    public $options = [];
}
