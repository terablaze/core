<?php

namespace Terablaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class ManyToOne implements Annotation
{
    /**
     * @var string
     */
    public $targetEntity;

    /**
     * @var array<string>
     */
    public $cascade;

    /**
     * The fetching strategy to use for the association.
     *
     * @var string
     *
     * @Enum({"LAZY", "EAGER", "EXTRA_LAZY"})
     */
    public $fetch = 'LAZY';

    /**
     * @var string
     */
    public $inversedBy;
}
