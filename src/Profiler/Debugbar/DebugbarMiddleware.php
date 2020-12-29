<?php

namespace TeraBlaze\Profiler\Debugbar;

use DebugBar\DebugBar as Bar;
use DebugBar\StandardDebugBar;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Profiler\Debugbar\DataCollectors\MySqliCollector;

class DebugbarMiddleware implements MiddlewareInterface
{
    private static $mimes = [
        'css' => 'text/css',
        'js' => 'text/javascript',
    ];

    /**
     * @var Bar|null The debugbar
     */
    private $debugbar;

    /**
     * @var bool Whether send data using headers in ajax requests
     */
    private $captureAjax = false;

    /**
     * @var bool Whether dump the css/js code inline in the html
     */
    private $inline = false;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var string $appUrl
     */
    private $appUrl;

    /**
     * Set the debug bar.
     * @param Bar|null $debugbar
     * @param ResponseFactoryInterface|null $responseFactory
     * @param StreamFactoryInterface|null $streamFactory
     */
    public function __construct(
        Bar $debugbar = null,
        ResponseFactoryInterface $responseFactory = null,
        StreamFactoryInterface $streamFactory = null
    ) {
        $this->debugbar = $debugbar ?: new StandardDebugBar();
        $this->responseFactory = $responseFactory ?: Factory::getResponseFactory();
        $this->streamFactory = $streamFactory ?: Factory::getStreamFactory();
    }

    /**
     * Configure whether capture ajax requests to send the data with headers.
     */
    public function captureAjax(bool $captureAjax = true): self
    {
        $this->captureAjax = $captureAjax;

        return $this;
    }

    /**
     * Configure whether the js/css code should be inserted inline in the html.
     */
    public function inline(bool $inline = true): self
    {
        $this->inline = $inline;

        return $this;
    }

    public function setAppUrl(string $appUrl): self
    {
        $this->appUrl = $appUrl;

        return $this;
    }

    /**
     * Process a server request and return a response.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $renderer = $this->debugbar->getJavascriptRenderer($this->appUrl . '/vendor/maximebf/debugbar/src/DebugBar/Resources/');

        //Asset response
        $path = $request->getUri()->getPath();
        $baseUrl = $renderer->getBaseUrl();

        if (strpos($path, $baseUrl) === 0) {
            $file = $renderer->getBasePath().substr($path, strlen($baseUrl));

            if (file_exists($file)) {
                $response = $this->responseFactory->createResponse();
                $response->getBody()->write((string) file_get_contents($file));
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if (isset(self::$mimes[$extension])) {
                    return $response->withHeader('Content-Type', self::$mimes[$extension]);
                }

                return $response; //@codeCoverageIgnore
            }
        }

        $response = $handler->handle($request);

        $container = Container::getContainer();

        $this->debugbar->addCollector(new MySqliCollector($container->get('ripana.database.connector')->getQueryLogger()));

        $isAjax = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';

        //Redirection response
        if (in_array($response->getStatusCode(), [302, 301])) {
            return $this->handleRedirect($response);
        }

        //Html response
        if (stripos($response->getHeaderLine('Content-Type'), 'text/html') === 0) {
            return $this->handleHtml($response, $isAjax);
        }

        //Ajax response
        if ($isAjax && $this->captureAjax) {
            $headers = $this->debugbar->getDataAsHeaders();

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Handle redirection responses
     */
    private function handleRedirect(ResponseInterface $response): ResponseInterface
    {
        if ($this->debugbar->isDataPersisted() || session_status() === PHP_SESSION_ACTIVE) {
            $this->debugbar->stackData();
        }

        return $response;
    }

    /**
     * Handle html responses
     */
    private function handleHtml(ResponseInterface $response, bool $isAjax): ResponseInterface
    {
        $html = (string) $response->getBody();
        $renderer = $this->debugbar->getJavascriptRenderer();

        if (!$isAjax) {
            if ($this->inline) {
                ob_start();
                echo "<style>\n";
                $renderer->dumpCssAssets();
                echo "\n</style>";
                echo "<script>\n";
                $renderer->dumpJsAssets();
                echo "\n</script>";
                $code = (string) ob_get_clean();
            } else {
                $code = $renderer->renderHead();
            }

            $html = self::injectHtml($html, $code, '</head>');
        }

        $html = self::injectHtml($html, $renderer->render(!$isAjax), '</body>');

        $body = $this->streamFactory->createStream();
        $body->write($html);

        return $response
            ->withBody($body)
            ->withoutHeader('Content-Length');
    }

    /**
     * Inject html code before a tag.
     */
    private static function injectHtml(string $html, string $code, string $before): string
    {
        $pos = strripos($html, $before);

        if ($pos === false) {
            return $html.$code;
        }

        return substr($html, 0, $pos).$code.substr($html, $pos);
    }

    public function getDebugBar(): Bar
    {
        return $this->debugbar;
    }
}