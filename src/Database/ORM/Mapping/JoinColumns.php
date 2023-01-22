<?php

namespace Terablaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class JoinColumns implements Annotation
{
    /**
     * @var array<\Terablaze\Database\ORM\Mapping\JoinColumn>
     */
    public $value;
}
