<?php

namespace TeraBlaze\Ripana\ORM;

use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Ripana\Database\Connectors\ConnectorInterface;
use TeraBlaze\Ripana\ORM\Exception\EntityNotFoundException;
use TeraBlaze\Ripana\ORM\Repository\EntityRepository;

class EntityManager
{
    /** @var Container $inspector */
    protected $container;

    /** @var ConnectorInterface $connector */
    protected $connector;

    public function __construct(ConnectorInterface $connector)
    {
        $this->container = Container::getContainer();
        $this->connector = $connector;
    }

    public function getDatabaseConfName(): string
    {
        return $this->connector->getDatabaseConfName();
    }

    public function getRepository(string $entity): EntityRepository
    {
        if (!class_exists($entity)) {
            throw new EntityNotFoundException("Class {$entity} does not exist");
        }
        $inspector = new Inspector($entity);
        $classMeta = $inspector->getClassMeta();
        $repositoryClass = $classMeta['@repository'][0] ?? (str_replace(['Model', 'Entity'], 'Repository', $entity)) . "Repository";
        $this->container->registerService($repositoryClass, [
            'class' => $repositoryClass,
            'arguments' => ['@ripana.orm.entity_manager.' . $this->getDatabaseConfName(), $entity],
        ]);
        return $this->container->get($repositoryClass);
    }

    public function getConnector(): ConnectorInterface
    {
        return $this->connector;
    }
}
