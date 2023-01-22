<?php

namespace Terablaze\Queue\Console\Command;

use Terablaze\Console\Command;
use Terablaze\Cache\Psr16\SimpleCacheInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Support\Traits\TimeAware;

#[AsCommand(name: 'queue:restart')]
class RestartCommand extends Command
{
    use TimeAware;

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'queue:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Restart queue worker daemons after their current job';

    /**
     * The cache store implementation.
     *
     * @var SimpleCacheInterface
     */
    protected $cache;

    /**
     * Create a new queue restart command.
     *
     * @param  SimpleCacheInterface  $cache
     * @return void
     */
    public function __construct(SimpleCacheInterface $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->set('terablaze:queue:restart', $this->currentTime(), 0);

        $this->io->info('Broadcasting queue restart signal.');
    }
}
