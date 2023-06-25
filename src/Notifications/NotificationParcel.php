<?php

namespace Terablaze\Notifications;

use Terablaze\Console\Application;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Notifications\Console\Commands\NotificationTableCommand;

class NotificationParcel extends Parcel
{
    /**
     * Boot the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'notifications');

        if ($this->getKernel()->inConsole()) {
            $this->publishes([
                __DIR__ . '/resources/views' => $this->getKernel()->resourceDir('views/vendor/notifications'),
            ], 'terablaze-notifications');
        }
        $this->register();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->container->registerServiceInstance(ChannelManager::class, new ChannelManager($this->container));

        $this->container->setAlias(
            DispatcherInterface::class, ChannelManager::class
        );

        $this->container->setAlias(
            ChannelMangerInterface::class, ChannelManager::class
        );
    }

    public function registerCommands(Application $application)
    {
        $application->add($this->container->make(NotificationTableCommand::class));
    }
}
