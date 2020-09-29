<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Ripana\Database\Connector\Mysql;
use TeraBlaze\Ripana\ORM\EntityManager;
use Tests\Model\User;

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";

$connector = new Mysql([
    "type" => 'mysqli',
    "host" => 'localhost',
    "username" => 'root',
    "password" => 'teraboxx',
    "schema" => 'billy',
    "port" => 3306,
]);

$container = Container::getContainer();

$container->registerServiceInstance('ripana.database.connector.default', $connector);

$entityManager = new EntityManager($container->get('ripana.database.connector.default'));
$container->registerServiceInstance('ripana.orm.entity_manager.default', $entityManager);

dump($entityManager);

$userRepo = $entityManager->getRepository(User::class);

dump($userRepo->getQueryBuilder());

$user = new User();

dump($connector->buildSyncSQL($user));

