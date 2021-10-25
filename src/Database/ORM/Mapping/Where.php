<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class Where implements Annotation
{
    /**
     * @var string
     */
    public $value;
}
