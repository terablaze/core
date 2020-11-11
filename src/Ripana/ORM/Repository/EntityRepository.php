<?php

namespace TeraBlaze\Ripana\ORM\Repository;

use TeraBlaze\Container\Container;
use TeraBlaze\Ripana\Database\QueryInterface;
use TeraBlaze\Ripana\ORM\EntityManager;
use TeraBlaze\Ripana\ORM\Exception\Connector;
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

    /**
     * @return QueryInterface
     * @throws Connector
     */
    public function getQueryBuilder(): QueryInterface
    {
        return $this->entity->getConnector()->query();
    }
}

