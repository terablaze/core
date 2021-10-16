<?php

namespace TeraBlaze\ErrorHandler;

use ErrorException;
use Exception;
use Symfony\Component\Console\Output\ConsoleOutput;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Exception\FatalError;
use TeraBlaze\Core\Kernel\Kernel;
use Throwable;

class HandleExceptions
{
    /**
     * Reserved memory so that errors can be displayed properly on memory exhaustion.
     *
     * @var string|null
     */
    public static ?string $reservedMemory;

    /**
     * The kernel instance.
     *
     * @var Kernel
     */
    protected Kernel $kernel;

    protected bool $debugMode = true;

    /**
     * Bootstrap the given application.
     *
     * @return void
     */
    public function bootstrap(Kernel $kernel)
    {
        self::$reservedMemory = str_repeat('x', 10240);

        $this->kernel = $kernel;

        error_reporting(-1);

        set_error_handler([$this, 'handleError']);

        set_exception_handler([$this, 'handleException']);

        register_shutdown_function([$this, 'handleShutdown']);

        if (!$kernel->isDebug()) {
            ini_set('display_errors', 'Off');
            $this->debugMode = false;
        }
    }

    /**
     * Convert PHP errors to ErrorException instances.
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @return void
     *
     * @throws ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0): void
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (!is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalErrorFromPhpError($error, 0));
        }
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * Note: Most exceptions can be handled via the try / catch block in
     * the HTTP and Console kernels. But, fatal error exceptions must
     * be handled differently since they are not normal exceptions.
     *
     * @param Throwable $e
     * @return void
     */
    public function handleException(Throwable $e)
    {
        if (ob_get_level() > 0) {
            ob_get_clean();
        }
        try {
            self::$reservedMemory = null;

            $this->getExceptionHandler()->report($e);
        } catch (Throwable $e) {
            //
        }

        if ($this->kernel->inConsole()) {
            $this->renderForConsole($e);
        } else {
            $this->renderHttpResponse($e);
        }
    }

    /**
     * Render an exception to the console.
     *
     * @param  \Throwable  $e
     * @return void
     */
    protected function renderForConsole(Throwable $e)
    {
        $this->getExceptionHandler()->renderForConsole(new ConsoleOutput(), $e);
    }

    /**
     * Create a new fatal error instance from an error array.
     *
     * @param array $error
     * @param int|null $traceOffset
     * @return FatalError
     */
    protected function fatalErrorFromPhpError(array $error, $traceOffset = null): FatalError
    {
        return new FatalError($error['message'], 0, $error, $traceOffset);
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param int $type
     * @return bool
     */
    protected function isFatal(int $type): bool
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param Throwable $e
     * @return void
     */
    protected function renderHttpResponse(Throwable $e)
    {
        $this->getExceptionHandler()->render($e, $this->kernel->getCurrentRequest())->send();
    }

    /**
     * Get an instance of the exception handler.
     *
     * @return ExceptionHandlerInterface
     */
    protected function getExceptionHandler()
    {
        try {
            $container = $this->kernel->getContainer();
        } catch (Exception $e) {
            $container = Container::getContainer();
        }
        return $container->make(ExceptionHandlerInterface::class, [
            'class' => ExceptionHandler::class,
            'arguments' => [$container, $this->kernel->isDebug()]
        ]);
    }
}
