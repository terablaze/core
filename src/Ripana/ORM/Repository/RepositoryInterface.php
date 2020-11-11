<?php

namespace TeraBlaze\Ripana\ORM\Repository;

use TeraBlaze\Ripana\Database\QueryInterface;

interface RepositoryInterface
{
    public function getQueryBuilder(): QueryInterface;
}