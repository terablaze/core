<?php

namespace Terablaze\Database\ORM\Mapping;

/**
 * @Annotation
 * @Target({"PROPERTY","ANNOTATION"})
 */
final class Pagination implements Annotation
{
    /**
     * @var string
     */
    public $limit = 30;

    /**
     * @var string
     */
    public $type = "page";

    /**
     * The name of the pagination query or lastItemId query
     *
     * @var string
     */
    public $query = "page";

    /**
     * Value can be 'request.post', 'request.get', 'cookie', 'session'.
     *
     * @var string
     */
    public $source = "request.get";
}
