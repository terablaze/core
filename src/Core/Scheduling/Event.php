<?php

namespace Terablaze\Core\Scheduling;

use Carbon\Carbon;
use Closure;
use Cron\CronExpression;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\TransferException;
use LogicException;
use ReflectionException;
use Terablaze\Container\ContainerInterface;
use Terablaze\Container\Exception\ContainerException;
use Terablaze\Container\Exception\ParameterNotFoundException;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;
use Terablaze\Support\Reflector;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Process\Process;
use Terablaze\Support\Stringable;
use Terablaze\Support\Traits\Macroable;
use Terablaze\Support\Traits\ReflectsClosures;
use Throwable;

class Event
{
    use Macroable;
    use ManagesFrequencies;
    use ReflectsClosures;

    /**
     * The command string.
     *
     * @var string
     */
    public $command;

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * *';

    /**
     * The timezone the date should be evaluated on.
     *
     * @var DateTimeZone|string
     */
    public $timezone;

    /**
     * The user the command should run as.
     *
     * @var string
     */
    public $user;

    /**
     * The list of environments the command should run under.
     *
     * @var array
     */
    public array $environments = [];

    /**
     * Indicates if the command should run in maintenance mode.
     *
     * @var bool
     */
    public bool $evenInMaintenanceMode = false;

    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public bool $withoutOverlapping = false;

    /**
     * Indicates if the command should only be allowed to run on one server for each cron expression.
     *
     * @var bool
     */
    public bool $onOneServer = false;

    /**
     * The amount of time the mutex should be valid.
     *
     * @var int
     */
    public int $expiresAt = 1440;

    /**
     * Indicates if the command should run in the background.
     *
     * @var bool
     */
    public bool $runInBackground = false;

    /**
     * The array of filter callbacks.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * The array of reject callbacks.
     *
     * @var array
     */
    protected array $rejects = [];

    /**
     * The location that output should be sent to.
     *
     * @var string|null
     */
    public ?string $output = '/dev/null';

    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    public bool $shouldAppendOutput = false;

    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array
     */
    protected array $beforeCallbacks = [];

    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected array $afterCallbacks = [];

    /**
     * The human-readable description of the event.
     *
     * @var string|null
     */
    public ?string $description = null;

    /**
     * The event mutex implementation.
     *
     * @var EventMutexInterface
     */
    public EventMutexInterface $mutex;

    /**
     * The mutex name resolver callback.
     *
     * @var Closure|null
     */
    public ?Closure $mutexNameResolver;

    /**
     * The exit status code of the command.
     *
     * @var int|null
     */
    public ?int $exitCode = null;

    /**
     * Create a new event instance.
     *
     * @param  EventMutexInterface  $mutex
     * @param string $command
     * @param DateTimeZone|string|null $timezone
     * @return void
     */
    public function __construct(EventMutexInterface $mutex, $command, DateTimeZone|string|null $timezone = null)
    {
        $this->mutex = $mutex;
        $this->command = $command;
        $this->timezone = $timezone;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string
     */
    public function getDefaultOutput(): string
    {
        return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
    }

    /**
     * Run the given event.
     *
     * @param  ContainerInterface  $container
     * @return void|mixed
     *
     * @throws Throwable
     */
    public function run(ContainerInterface $container)
    {
        if ($this->shouldSkipDueToOverlapping()) {
            return;
        }

        $exitCode = $this->start($container);

        if (! $this->runInBackground) {
            $this->finish($container, $exitCode);
        }
    }

    /**
     * Determine if the event should skip because another process is overlapping.
     *
     * @return bool
     */
    public function shouldSkipDueToOverlapping(): bool
    {
        return $this->withoutOverlapping && ! $this->mutex->create($this);
    }

    /**
     * Run the command process.
     *
     * @param ContainerInterface $container
     * @return int
     *
     * @throws Throwable
     */
    protected function start(ContainerInterface $container): int
    {
        try {
            $this->callBeforeCallbacks($container);

            return $this->execute($container);
        } catch (Throwable $exception) {
            $this->removeMutex();

            throw $exception;
        }
    }

    /**
     * Run the command process.
     *
     * @param ContainerInterface $container
     * @return int
     */
    protected function execute(ContainerInterface $container): int
    {
        return Process::fromShellCommandline(
            $this->buildCommand(), baseDir(), null, null, null
        )->run();
    }

    /**
     * Mark the command process as finished and run callbacks/cleanup.
     *
     * @param ContainerInterface $container
     * @param int $exitCode
     * @return void
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function finish(ContainerInterface $container, int $exitCode): void
    {
        $this->exitCode = $exitCode;

        try {
            $this->callAfterCallbacks($container);
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * Call all the "before" callbacks for the event.
     *
     * @param ContainerInterface $container
     * @return void
     * @throws ReflectionException
     * @throws ContainerException
     * @throws ParameterNotFoundException
     */
    public function callBeforeCallbacks(ContainerInterface $container): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Call all the "after" callbacks for the event.
     *
     * @param ContainerInterface $container
     * @return void
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function callAfterCallbacks(ContainerInterface $container): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand(): string
    {
        return (new CommandBuilder)->buildCommand($this);
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @param KernelInterface $kernel
     * @return bool
     */
    public function isDue(KernelInterface $kernel): bool
    {
        if (! $this->runsInMaintenanceMode() && $kernel->isDownForMaintenance()) {
            return false;
        }

        return $this->expressionPasses() &&
            $this->runsInEnvironment($kernel->getEnvironment());
    }

    /**
     * Determine if the event runs in maintenance mode.
     *
     * @return bool
     */
    public function runsInMaintenanceMode(): bool
    {
        return $this->evenInMaintenanceMode;
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses(): bool
    {
        $date = Carbon::now();

        if ($this->timezone) {
            $date = $date->setTimezone($this->timezone);
        }

        return (new CronExpression($this->expression))->isDue($date->toDateTimeString());
    }

    /**
     * Determine if the event runs in the given environment.
     *
     * @param string $environment
     * @return bool
     */
    public function runsInEnvironment(string $environment): bool
    {
        return empty($this->environments) || in_array($environment, $this->environments);
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @param ContainerInterface $container
     * @return bool
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function filtersPass(ContainerInterface $container): bool
    {
        foreach ($this->filters as $callback) {
            if (! $container->call($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if ($container->call($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure that the output is stored on disk in a log file.
     *
     * @return $this
     * @throws ReflectionException
     */
    public function storeOutput(): static
    {
        $this->ensureOutputIsBeingCaptured();

        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param string $location
     * @param bool $append
     * @return $this
     */
    public function sendOutputTo(string $location, bool $append = false): static
    {
        $this->output = $location;

        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param string $location
     * @return $this
     */
    public function appendOutputTo(string $location): static
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * E-mail the results of the scheduled operation.
     *
     * @param  array|mixed  $addresses
     * @param bool $onlyIfOutputExists
     * @return $this
     *
     * @throws LogicException
     */
//    public function emailOutputTo(mixed $addresses, bool $onlyIfOutputExists = false): static
//    {
//        $this->ensureOutputIsBeingCaptured();
//
//        $addresses = ArrayMethods::wrap($addresses);
//
//        return $this->then(function (Mailer $mailer) use ($addresses, $onlyIfOutputExists) {
//            $this->emailOutput($mailer, $addresses, $onlyIfOutputExists);
//        });
//    }

    /**
     * E-mail the results of the scheduled operation if it produces output.
     *
     * @param  array|mixed  $addresses
     * @return $this
     *
     * @throws LogicException
     */
//    public function emailWrittenOutputTo($addresses): static
//    {
//        return $this->emailOutputTo($addresses, true);
//    }

    /**
     * E-mail the results of the scheduled operation if it fails.
     *
     * @param  array|mixed  $addresses
     * @return $this
     */
//    public function emailOutputOnFailure($addresses): static
//    {
//        $this->ensureOutputIsBeingCaptured();
//
//        $addresses = ArrayMethods::wrap($addresses);
//
//        return $this->onFailure(function (Mailer $mailer) use ($addresses) {
//            $this->emailOutput($mailer, $addresses, false);
//        });
//    }

    /**
     * Ensure that the command output is being captured.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function ensureOutputIsBeingCaptured(): void
    {
        if (is_null($this->output) || $this->output == $this->getDefaultOutput()) {
            $this->sendOutputTo(storageDir('logs/' . kernel()->getEnvironment() . '/schedule-'.sha1($this->mutexName()).'.log'));
        }
    }

    /**
//     * E-mail the output of the event to the recipients.
//     *
//     * @param  \Illuminate\Contracts\Mail\Mailer  $mailer
//     * @param  array  $addresses
//     * @param  bool  $onlyIfOutputExists
//     * @return void
     */
//    protected function emailOutput(Mailer $mailer, $addresses, $onlyIfOutputExists = false): void
//    {
//        $text = is_file($this->output) ? file_get_contents($this->output) : '';
//
//        if ($onlyIfOutputExists && empty($text)) {
//            return;
//        }
//
//        $mailer->raw($text, function ($m) use ($addresses) {
//            $m->to($addresses)->subject($this->getEmailSubject());
//        });
//    }

    /**
     * Get the e-mail subject line for output results.
     *
     * @return string
     */
//    protected function getEmailSubject(): string
//    {
//        if ($this->description) {
//            return $this->description;
//        }
//
//        return "Scheduled Job Output For [{$this->command}]";
//    }

    /**
     * Register a callback to ping a given URL before the job runs.
     *
     * @param string $url
     * @return $this
     */
    public function pingBefore(string $url): static
    {
        return $this->before($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL before the job runs if the given condition is true.
     *
     * @param bool $value
     * @param string $url
     * @return $this
     */
    public function pingBeforeIf(bool $value, string $url): static
    {
        return $value ? $this->pingBefore($url) : $this;
    }

    /**
     * Register a callback to ping a given URL after the job runs.
     *
     * @param string $url
     * @return $this
     * @throws ReflectionException
     */
    public function thenPing(string $url): static
    {
        return $this->then($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL after the job runs if the given condition is true.
     *
     * @param bool $value
     * @param string $url
     * @return $this
     * @throws ReflectionException
     */
    public function thenPingIf(bool $value, string $url): Event|static
    {
        return $value ? $this->thenPing($url) : $this;
    }

    /**
     * Register a callback to ping a given URL if the operation succeeds.
     *
     * @param string $url
     * @return $this
     * @throws ReflectionException
     */
    public function pingOnSuccess(string $url): static
    {
        return $this->onSuccess($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL if the operation fails.
     *
     * @param string $url
     * @return $this
     * @throws ReflectionException
     */
    public function pingOnFailure(string $url): static
    {
        return $this->onFailure($this->pingCallback($url));
    }

    /**
     * Get the callback that pings the given URL.
     *
     * @param string $url
     * @return Closure
     */
    protected function pingCallback(string $url): Closure
    {
        return function (ContainerInterface $container, HttpClient $http) use ($url) {
            try {
                $http->request('GET', $url);
            } catch (ClientExceptionInterface|TransferException $e) {
                Helpers::kernel()->getExceptionHandler()->report($e);
            }
        };
    }

    /**
     * State that the command should run in the background.
     *
     * @return $this
     */
    public function runInBackground(): static
    {
        $this->runInBackground = true;

        return $this;
    }

    /**
     * Set which user the command should run as.
     *
     * @param string $user
     * @return $this
     */
    public function user(string $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Limit the environments the command should run in.
     *
     * @param  array|mixed  $environments
     * @return $this
     */
    public function environments(mixed $environments): static
    {
        $this->environments = is_array($environments) ? $environments : func_get_args();

        return $this;
    }

    /**
     * State that the command should run even in maintenance mode.
     *
     * @return $this
     */
    public function evenInMaintenanceMode(): static
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @param int $expiresAt
     * @return $this
     */
    public function withoutOverlapping(int $expiresAt = 1440): static
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->skip(function () {
            return $this->mutex->exists($this);
        });
    }

    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     */
    public function onOneServer(): static
    {
        $this->onOneServer = true;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param bool|Closure $callback
     * @return $this
     */
    public function when(bool|Closure $callback): static
    {
        $this->filters[] = Reflector::isCallable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param bool|Closure $callback
     * @return $this
     */
    public function skip(bool|Closure $callback): static
    {
        $this->rejects[] = Reflector::isCallable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to be called before the operation.
     *
     * @param Closure $callback
     * @return $this
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param Closure $callback
     * @return $this
     * @throws ReflectionException
     */
    public function after(Closure $callback): static
    {
        return $this->then($callback);
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param Closure $callback
     * @return $this
     * @throws ReflectionException
     */
    public function then(Closure $callback): static
    {
        $parameters = $this->closureParameterTypes($callback);

        if (ArrayMethods::get($parameters, 'output') === Stringable::class) {
            return $this->thenWithOutput($callback);
        }

        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback that uses the output after the job runs.
     *
     * @param Closure $callback
     * @param bool $onlyIfOutputExists
     * @return $this
     * @throws ReflectionException
     */
    public function thenWithOutput(Closure $callback, bool $onlyIfOutputExists = false): static
    {
        $this->ensureOutputIsBeingCaptured();

        return $this->then($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Register a callback to be called if the operation succeeds.
     *
     * @param Closure $callback
     * @return $this
     * @throws ReflectionException
     */
    public function onSuccess(Closure $callback): static
    {
        $parameters = $this->closureParameterTypes($callback);

        if (ArrayMethods::get($parameters, 'output') === Stringable::class) {
            return $this->onSuccessWithOutput($callback);
        }

        return $this->then(function (ContainerInterface $container) use ($callback) {
            if ($this->exitCode === 0) {
                $container->call($callback);
            }
        });
    }

    /**
     * Register a callback that uses the output if the operation succeeds.
     *
     * @param Closure $callback
     * @param bool $onlyIfOutputExists
     * @return $this
     * @throws ReflectionException
     */
    public function onSuccessWithOutput(Closure $callback, bool $onlyIfOutputExists = false): static
    {
        $this->ensureOutputIsBeingCaptured();

        return $this->onSuccess($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Register a callback to be called if the operation fails.
     *
     * @param Closure $callback
     * @return $this
     * @throws ReflectionException
     */
    public function onFailure(Closure $callback): static
    {
        $parameters = $this->closureParameterTypes($callback);

        if (ArrayMethods::get($parameters, 'output') === Stringable::class) {
            return $this->onFailureWithOutput($callback);
        }

        return $this->then(function (ContainerInterface $container) use ($callback) {
            if ($this->exitCode !== 0) {
                $container->call($callback);
            }
        });
    }

    /**
     * Register a callback that uses the output if the operation fails.
     *
     * @param Closure $callback
     * @param bool $onlyIfOutputExists
     * @return $this
     * @throws ReflectionException
     */
    public function onFailureWithOutput(Closure $callback, bool $onlyIfOutputExists = false): static
    {
        $this->ensureOutputIsBeingCaptured();

        return $this->onFailure($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Get a callback that provides output.
     *
     * @param Closure $callback
     * @param  bool  $onlyIfOutputExists
     * @return Closure
     */
    protected function withOutputCallback(Closure $callback, $onlyIfOutputExists = false): Closure
    {
        return function (ContainerInterface $container) use ($callback, $onlyIfOutputExists) {
            $output = $this->output && is_file($this->output) ? file_get_contents($this->output) : '';

            return $onlyIfOutputExists && empty($output)
                ? null
                : $container->call($callback, ['output' => new Stringable($output)]);
        };
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description
     * @return $this
     */
    public function name(string $description): static
    {
        return $this->description($description);
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description
     * @return $this
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay(): string
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return $this->buildCommand();
    }

    /**
     * Determine the next due date for an event.
     *
     * @param DateTimeInterface|string $currentTime
     * @param int $nth
     * @param bool $allowCurrentDate
     * @return Carbon
     * @throws Exception
     */
    public function nextRunDate(DateTimeInterface|string $currentTime = 'now', $nth = 0, $allowCurrentDate = false): Carbon
    {
        return Carbon::instance((new CronExpression($this->getExpression()))
            ->getNextRunDate($currentTime, $nth, $allowCurrentDate, $this->timezone));
    }

    /**
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Set the event mutex implementation to be used.
     *
     * @param  EventMutexInterface  $mutex
     * @return $this
     */
    public function preventOverlapsUsing(EventMutexInterface $mutex): static
    {
        $this->mutex = $mutex;

        return $this;
    }

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string
     */
    public function mutexName(): string
    {
        $mutexNameResolver = $this->mutexNameResolver;

        if (! is_null($mutexNameResolver) && is_callable($mutexNameResolver)) {
            return $mutexNameResolver($this);
        }

        return 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($this->expression.$this->command);
    }

    /**
     * Set the mutex name or name resolver callback.
     *
     * @param Closure|string  $mutexName
     * @return $this
     */
    public function createMutexNameUsing(Closure|string $mutexName): static
    {
        $this->mutexNameResolver = is_string($mutexName) ? fn () => $mutexName : $mutexName;

        return $this;
    }

    /**
     * Delete the mutex for the event.
     *
     * @return void
     */
    protected function removeMutex(): void
    {
        if ($this->withoutOverlapping) {
            $this->mutex->forget($this);
        }
    }
}
