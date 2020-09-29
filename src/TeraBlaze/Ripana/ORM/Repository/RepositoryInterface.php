<?php

namespace TeraBlaze\Ripana\ORM\Repository;

use TeraBlaze\Ripana\Database\Query\Query;

interface RepositoryInterface
{
    public function getQueryBuilder(): Query;
}