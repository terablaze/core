<?php

namespace Terablaze\Bus;

use Terablaze\Bus\Dispatcher;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Queue\QueueManagerInterface as QueueFactoryInterface;
use Terablaze\Support\Helpers;

class BusParcel extends Parcel
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->container->registerServiceInstance(
            Dispatcher::class,
            new Dispatcher($this->container, function ($connection = null) {
                return $this->container->make(QueueFactoryInterface::class)->connection($connection);
            })
        );

        $this->registerBatchServices();

        $this->container->setAlias(DispatcherInterface::class, Dispatcher::class);

        $this->container->setAlias(QueueingDispatcherInterface::class, Dispatcher::class);
    }

    /**
     * Register the batch handling services.
     *
     * @return void
     */
    protected function registerBatchServices()
    {
        $this->container->registerServiceInstance(
            BatchRepositoryInterface::class,
            new DatabaseBatchRepository(
                $this->container->make(BatchFactory::class),
                $this->container->get('database.connection' . Helpers::getConfig('queue.batching.database')),
                Helpers::getConfig('queue.batching.table', 'job_batches')
            )
        );
    }
}
