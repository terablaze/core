<?php

namespace TeraBlaze\Core\Console\Command;

use Symfony\Component\Console\Input\InputOption;
use TeraBlaze\Console\Command;
use TeraBlaze\Filesystem\Files;

class StorageLinkCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected static $defaultName= 'storage:link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create the symbolic links configured for the application';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $relative = $this->input->getOption('relative');

        foreach ($this->links() as $link => $target) {
            if (file_exists($link)) {
                $this->io->error("The [$link] link already exists.");
                continue;
            }

            if ($relative) {
                $this->container->make(Files::class)->relativeLink($target, $link);
            } else {
                $this->container->make(Files::class)->link($target, $link);
            }

            $this->io->info("The [$link] link has been connected to [$target].");
        }

        $this->io->info('The links have been created.');
        return self::SUCCESS;
    }

    /**
     * Get the symbolic links that are configured for the application.
     *
     * @return array
     */
    protected function links()
    {
        return getConfig('filesystems.links') ??
               [publicDir('storage') => storageDir('app/public')];
    }

    protected function getOptions()
    {
        return [
            ['relative', null, InputOption::VALUE_NONE, 'Create the symbolic link using relative paths'],
        ];
    }
}
