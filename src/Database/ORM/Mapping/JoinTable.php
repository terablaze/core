<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target({"PROPERTY","ANNOTATION"})
 */
final class JoinTable implements Annotation
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
     * @var array<\TeraBlaze\Database\ORM\Mapping\JoinColumn>
     */
    public $joinColumns = [];

    /**
     * @var array<\TeraBlaze\Database\ORM\Mapping\JoinColumn>
     */
    public $inverseJoinColumns = [];
}
