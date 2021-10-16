<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class Limit implements Annotation
{
    /**
     * @var int
     */
    public $value;
}
