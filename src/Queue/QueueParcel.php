<?php

namespace Terablaze\Queue;

use Terablaze\Queue\Connectors\DatabaseConnector;
use Terablaze\Queue\Connectors\NullConnector;
use Terablaze\Queue\Connectors\RedisConnector;
use Terablaze\Queue\Connectors\SyncConnector;
use Terablaze\Console\Application;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\Queue\Console\Command;
use Terablaze\Queue\Failed\DatabaseFailedJobProvider;
use Terablaze\Queue\Failed\DatabaseUuidFailedJobProvider;
use Terablaze\Queue\Failed\NullFailedJobProvider;
use Terablaze\SerializableClosure\SerializableClosure;
use Terablaze\Support\Helpers;

class QueueParcel extends Parcel implements ParcelInterface
{
    use SerializesAndRestoresModelIdentifiers;

    protected QueueManager $queueManager;

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        Command\BatchesTableCommand::class,
        Command\ClearCommand::class,
        Command\FailedTableCommand::class,
        Command\FlushFailedCommand::class,
        Command\ForgetFailedCommand::class,
        Command\ListenCommand::class,
        Command\ListFailedCommand::class,
        Command\MonitorCommand::class,
        Command\PruneBatchesCommand::class,
        Command\PruneFailedJobsCommand::class,
        Command\RestartCommand::class,
        Command\RetryBatchCommand::class,
        Command\RetryCommand::class,
        Command\TableCommand::class,
        Command\WorkCommand::class,
    ];

    public function boot(): void
    {
        $this->loadConfig('queue');

        $this->configureSerializableClosureUses();

        $this->registerManager();
        $this->registerConnection();
        $this->registerWorker();
        $this->registerListener();
        $this->registerFailedJobServices();
    }

    /**
     * @param string $confKey
     * @param array $conf
     * @return void
     */
    private function initialize(string $confKey, array $conf): void
    {
        $type = $conf['type'] ?? $conf['driver'] ?? '';

        $queueName = "queue.$confKey";
        if (empty($type)) {
            throw new \InvalidArgumentException("Queue driver type not set");
        }

        switch ($type) {
            case "database":
                $queueConnection = new DatabaseConnector($this->container);
                break;
            case "redis":
                $queueConnection = new RedisConnector($this->container);
                break;
            default:
                throw new \InvalidArgumentException(sprintf("Invalid or unimplemented queue driver type: %s", $type));
        }

        $queue = $queueConnection->connect($conf)->setConnectionName($confKey);
        $this->container->registerServiceInstance($queueName, $queue);
        if (getConfig('queue.default') === $confKey) {
            $this->container->setAlias(QueueInterface::class, $queueName);
            $this->container->setAlias('queue', $queueName);
            $this->container->setAlias('queue.default', $queueName);
        }
    }

    /**
     * @param Application $application
     * @return void
     * @throws \ReflectionException
     */
    public function registerCommands(Application $application)
    {
//        if (!$this->getKernel()->inConsole()) {
//            return;
//        }

        foreach ($this->commands as $command) {
            $application->add($this->container->make($command));
        }
    }

    /*
     * Configure serializable closures uses.
     *
     * @return void
     */
    protected function configureSerializableClosureUses()
    {
        SerializableClosure::transformUseVariablesUsing(function ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->getSerializedPropertyValue($value);
            }

            return $data;
        });

        SerializableClosure::resolveUseVariablesUsing(function ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->getRestoredPropertyValue($value);
            }

            return $data;
        });
    }

    /**
     * Register the queue manager.
     *
     * @return void
     */
    public function registerManager()
    {
        $this->container->registerServiceInstance(
            'queue',
            $this->queueManager = Helpers::tap(new QueueManager($this->container), function ($manager) {
                $this->registerConnectors($manager);
            })
        );
        $this->container->setAlias(QueueManagerInterface::class, 'queue');
    }

    /**
     * Register the default queue connection binding.
     *
     * @return void
     */
    protected function registerConnection()
    {
        $this->container->registerServiceInstance('queue.connection', $this->queueManager->connection());
    }

    /**
     * Register the connectors on the queue manager.
     *
     * @param QueueManager $manager
     * @return void
     */
    public function registerConnectors($manager)
    {
        $this->registerNullConnector($manager);
        $this->registerSyncConnector($manager);
        $this->registerDatabaseConnector($manager);
        $this->registerRedisConnector($manager);
    }

    /**
     * Register the Null queue connector.
     *
     * @param QueueManager $manager
     * @return void
     */
    protected function registerNullConnector($manager)
    {
        $manager->addConnector('null', function () {
            return new NullConnector();
        });
    }

    /**
     * Register the Sync queue connector.
     *
     * @param QueueManager $manager
     * @return void
     */
    protected function registerSyncConnector($manager)
    {
        $manager->addConnector('sync', function () {
            return new SyncConnector();
        });
    }

    /**
     * Register the database queue connector.
     *
     * @param QueueManager $manager
     * @return void
     */
    protected function registerDatabaseConnector($manager)
    {
        $manager->addConnector('database', function () {
            return new DatabaseConnector($this->container);
        });
    }

    /**
     * Register the redis queue connector.
     *
     * @param QueueManager $manager
     * @return void
     */
    protected function registerRedisConnector($manager)
    {
        $manager->addConnector('redis', function () {
            return new RedisConnector($this->container);
        });
    }

    /**
     * Register the queue worker.
     *
     * @return void
     */
    protected function registerWorker()
    {
        $workerResolver = function () {
            $isDownForMaintenance = function () {
                return $this->getKernel()->isDownForMaintenance();
            };

            $resetScope = function () {
                // TODO: implement reset scope
            };

            return new Worker(
                $this->container->get('queue'),
                $this->getKernel()->getEventDispatcher(),
                $this->getKernel()->getExceptionHandler(),
                $isDownForMaintenance,
                $resetScope
            );
        };
        $this->container->registerServiceInstance('queue.worker', $workerResolver());
    }

    /**
     * Register the queue listener.
     *
     * @return void
     */
    protected function registerListener()
    {
        $this->container->registerServiceInstance('queue.listener', new Listener($this->getKernel()->getProjectDir()));
    }

    /**
     * Register the failed job services.
     *
     * @return void
     */
    protected function registerFailedJobServices()
    {
        $failerResolver = function () {
            $config = $this->getKernel()->getConfig()->get('queue.failed', []);

            if (
                array_key_exists('driver', $config) &&
                (is_null($config['driver']) || $config['driver'] === 'null')
            ) {
                return new NullFailedJobProvider();
            }

            if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return $this->databaseUuidFailedJobProvider($config);
            } elseif (isset($config['table'])) {
                return $this->databaseFailedJobProvider($config);
            } else {
                return new NullFailedJobProvider();
            }
        };
        $this->container->registerServiceInstance('queue.failer', $failerResolver());
    }

    /**
     * Create a new database failed job provider.
     *
     * @param array $config
     * @return DatabaseFailedJobProvider
     */
    protected function databaseFailedJobProvider($config)
    {
        return new DatabaseFailedJobProvider(
            $this->container->get('database.connection.' . $config['database']),
            $config['database'],
            $config['table']
        );
    }

    /**
     * Create a new database failed job provider that uses UUIDs as IDs.
     *
     * @param array $config
     * @return DatabaseUuidFailedJobProvider
     */
    protected function databaseUuidFailedJobProvider($config)
    {
        return new DatabaseUuidFailedJobProvider(
            $this->container->get('database.connection.' . $config['database']),
            $config['database'],
            $config['table']
        );
    }
}
