<?php

namespace Terablaze\HttpBase\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\HttpBase\MaintenanceModeBypassCookie;
use Terablaze\HttpBase\RedirectResponse;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;
use Terablaze\View\View;

class PreventRequestsDuringMaintenance
{
    /**
     * The kernel implementation.
     *
     * @var KernelInterface
     */
    protected KernelInterface $kernel;

    /**
     * The URIs that should be accessible while maintenance mode is enabled.
     *
     * @var array
     */
    protected array $except = [];

    /**
     * Create a new middleware instance.
     *
     * @param KernelInterface $kernel
     * @return void
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws HttpException
     */
    public function handle($request, Closure $next)
    {
        if ($this->kernel->maintenanceMode()->active()) {
            $data = $this->kernel->maintenanceMode()->data();

            if (isset($data['secret']) && $request->path() === $data['secret']) {
                return $this->bypassResponse($data['secret']);
            }

            if ($this->hasValidBypassCookie($request, $data) ||
                $this->inExceptArray($request)) {
                return $next($request);
            }

            if (isset($data['redirect'])) {
                $path = $data['redirect'] === '/'
                            ? $data['redirect']
                            : trim($data['redirect'], '/');

                if ($request->path() !== $path) {
                    return new RedirectResponse($path);
                }
            }

            if (isset($data['template'])) {
                $content = ($data['template']);
                /** @var View $view */
                $view = $this->kernel->getContainer()->get(View::class);

                $template = $view->render($content);
                return new Response(
                    $template->render(),
                    $data['status'] ?? 503,
                    $this->getHeaders($data)
                );
            }

            throw new HttpException(
                $data['status'] ?? 503,
                'Service Unavailable',
                null,
                $this->getHeaders($data)
            );
        }

        return $next($request);
    }

    /**
     * Determine if the incoming request has a maintenance mode bypass cookie.
     *
     * @param  Request  $request
     * @param  array  $data
     * @return bool
     */
    protected function hasValidBypassCookie($request, array $data)
    {
        return isset($data['secret']) &&
                $request->getCookieParam('terablaze_maintenance') &&
                MaintenanceModeBypassCookie::isValid(
                    $request->getCookieParam('terablaze_maintenance'),
                    $data['secret']
                );
    }

    /**
     * Determine if the request has a URI that should be accessible in maintenance mode.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->getExcludedPaths() as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect the user back to the root of the application with a maintenance mode bypass cookie.
     *
     * @param  string  $secret
     * @return RedirectResponse
     */
    protected function bypassResponse(string $secret)
    {
        return (new RedirectResponse('/'))->withCookie(
            MaintenanceModeBypassCookie::create($secret)
        );
    }

    /**
     * Get the headers that should be sent with the response.
     *
     * @param  array  $data
     * @return array
     */
    protected function getHeaders($data)
    {
        $headers = isset($data['retry']) ? ['Retry-After' => $data['retry']] : [];

        if (isset($data['refresh'])) {
            $headers['Refresh'] = $data['refresh'];
        }

        return $headers;
    }

    /**
     * Get the URIs that should be accessible even when maintenance mode is enabled.
     *
     * @return array
     */
    public function getExcludedPaths()
    {
        return $this->except;
    }
}
