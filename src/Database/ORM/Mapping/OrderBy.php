<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class OrderBy implements Annotation
{
    /**
     * @var array<string>
     */
    public $value;
}
