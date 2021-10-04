<?php

namespace TeraBlaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class JoinColumns implements Annotation
{
    /**
     * @var array<\TeraBlaze\Database\ORM\Mapping\JoinColumn>
     */
    public $value;
}
