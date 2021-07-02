<?php

namespace TeraBlaze\Ripana\ORM\Repository;

use TeraBlaze\Ripana\Database\Query\QueryInterface;

interface RepositoryInterface
{
    public function getQueryBuilder(): QueryInterface;
}
