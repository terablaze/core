<?php

namespace TeraBlaze\Ripana\ORM\Repository;

use TeraBlaze\Container\Container;
use TeraBlaze\Ripana\Database\Query\Query;
use TeraBlaze\Ripana\ORM\EntityManager;
use TeraBlaze\Ripana\ORM\Model;

abstract class EntityRepository implements RepositoryInterface
{
    /** @var Container $inspector */
    protected $container;

    /** @var EntityManager $entityManager */
    protected $entityManager;

    /** @var Model $entity */
    protected $entity;


    public function __construct(EntityManager $entityManager, $entity)
    {
        $this->container = Container::getContainer();
        $this->entityManager = $entityManager;
        $this->entity = new $entity();
    }

    public function getQueryBuilder(): Query
    {
        return $this->entity->getConnector()->query();
    }
}

