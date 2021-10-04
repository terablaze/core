<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Table implements Annotation
{
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
     * @var array<\TeraBlaze\Database\ORM\Mapping\Index>
     */
    public $indexes;

    /**
     * @var array<\TeraBlaze\Database\ORM\Mapping\UniqueConstraint>
     */
    public $uniqueConstraints;

    /**
     * @var array
     */
    public $options = [];
}
