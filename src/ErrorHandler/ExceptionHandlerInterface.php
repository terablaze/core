<?php

namespace TeraBlaze\ErrorHandler;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use Throwable;

interface ExceptionHandlerInterface
{
    /**
     * Report or log an exception.
     *
     * @param  Throwable  $e
     * @return void
     *
     * @throws Throwable
     */
    public function report(Throwable $e);

    /**
     * Determine if the exception should be reported.
     *
     * @param  Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool;

    /**
     * Render an exception into an HTTP response.
     *
     * @param Throwable $e
     * @param ServerRequestInterface|Request|null $request
     * @return Response
     */
    public function render(Throwable $e, ?ServerRequestInterface $request = null): Response;

    /**
     * Render an exception to the console.
     *
     * @param OutputInterface $output
     * @param  Throwable  $e
     * @return void
     */
    public function renderForConsole(OutputInterface $output, Throwable $e): void;
}
