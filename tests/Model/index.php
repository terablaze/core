<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Ripana\Database\Drivers\Mysqli\Connector;
use TeraBlaze\Ripana\ORM\EntityManager;
use Tests\TeraBlaze\Model\User;

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";

$connector = new Connector([
    "type" => 'mysqli',
    "host" => 'localhost',
    "username" => 'root',
    "password" => 'teraboxx',
    "schema" => 'terablaze_core',
    "port" => 3306,
]);

$container = Container::getContainer();

$container->registerServiceInstance('ripana.database.connector.default', $connector);

dump($connector->buildSyncSQL(User::class));

$entityManager = new EntityManager($container->get('ripana.database.connector.default'));
$container->registerServiceInstance('ripana.orm.entity_manager.default', $entityManager);

dump($entityManager);
//
$userRepo = $entityManager->getRepository(User::class);

dump($userRepo->getQueryBuilder());

$user = new User();

dump($connector->sync($user));
$user->save();

$users = User::all(
    ['id IN ?' => [[11, 2, 3, 4, 5]]]
);

dump($users);
