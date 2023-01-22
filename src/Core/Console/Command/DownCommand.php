<?php

namespace Terablaze\Core\Console\Command;

//use App\Middleware\PreventRequestsDuringMaintenance;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\Core\MaintenanceMode\Events\MaintenanceModeEnabled;
use Terablaze\ErrorHandler\RegisterErrorViewPaths;
use Terablaze\HttpBase\Middleware\PreventRequestsDuringMaintenance;
use Terablaze\View\View;
use Throwable;

#[AsCommand(name: 'down')]
class DownCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'down';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'down';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Put the application into maintenance / demo mode';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            if ($this->kernel->maintenanceMode()->active()) {
                $this->io->info('Application is already down.');

                return 0;
            }

            $this->kernel->maintenanceMode()->activate($this->getDownFilePayload());

            file_put_contents(
                storageDir('framework/maintenance.php'),
                file_get_contents(__DIR__.'/stubs/maintenance-mode.stub')
            );

            $this->kernel->getEventDispatcher()->dispatch(new MaintenanceModeEnabled());

            $this->io->info('Application is now in maintenance mode.');
            return self::SUCCESS;
        } catch (Exception $e) {
            $this->io->error(sprintf(
                'Failed to enter maintenance mode: %s.',
                $e->getMessage(),
            ));

            return self::FAILURE;
        }
    }

    /**
     * Get the payload to be placed in the "down" file.
     *
     * @return array
     */
    protected function getDownFilePayload()
    {
        return [
            'except' => $this->excludedPaths(),
            'redirect' => $this->redirectPath(),
            'retry' => $this->getRetryTime(),
            'refresh' => $this->getOption('refresh'),
            'secret' => $this->getOption('secret'),
            'status' => (int) $this->getOption('status', 503),
            'template' => $this->getOption('render') ? $this->prerenderView() : null,
        ];
    }

    /**
     * Get the paths that should be excluded from maintenance mode.
     *
     * @return array
     */
    protected function excludedPaths()
    {
        try {
            return $this->container->make(PreventRequestsDuringMaintenance::class)->getExcludedPaths();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get the path that users should be redirected to.
     *
     * @return string
     */
    protected function redirectPath()
    {
        if ($this->getOption('redirect') && $this->getOption('redirect') !== '/') {
            return '/'.trim($this->getOption('redirect'), '/');
        }

        return $this->getOption('redirect');
    }

    /**
     * Prerender the specified view so that it can be rendered even before loading Composer.
     *
     * @return string
     */
    protected function prerenderView()
    {
        (new RegisterErrorViewPaths())();

        /** @var View $view */
        $view = $this->kernel->getContainer()->get(View::class);

        return $view->render($this->getOption('render'), [
            'retryAfter' => $this->getOption('retry'),
        ])->render();
    }

    /**
     * Get the number of seconds the client should wait before retrying their request.
     *
     * @return int|null
     */
    protected function getRetryTime()
    {
        $retry = $this->getKernel('retry');

        return is_numeric($retry) && $retry > 0 ? (int) $retry : null;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['redirect', null, InputOption::VALUE_OPTIONAL, 'The path that users should be redirected to'],
            ['render', null, InputOption::VALUE_OPTIONAL, 'The view that should be prerendered for display during maintenance mode'],
            ['retry', null, InputOption::VALUE_OPTIONAL, 'The number of seconds after which the request may be retried'],
            ['refresh', null, InputOption::VALUE_OPTIONAL, 'The number of seconds after which the browser may refresh'],
            ['secret', null, InputOption::VALUE_OPTIONAL, 'The secret phrase that may be used to bypass maintenance mode'],
            ['status', null, InputOption::VALUE_OPTIONAL, 'The status code that should be used when returning the maintenance mode response}', 503],
        ];
    }
}
