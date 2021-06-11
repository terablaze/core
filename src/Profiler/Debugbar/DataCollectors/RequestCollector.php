<?php

namespace TeraBlaze\Profiler\Debugbar\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\HttpBase\Session\Driver;

class RequestCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    /** @var Request $request */
    protected $request;
    /** @var  Response $response */
    protected $response;
    /** @var Driver\Server|null $session */
    protected $session;
    /** @var string|null */
    protected $currentRequestId;

    /**
     * Create a new SymfonyRequestCollector
     *
     * @param RequestInterface|Request $request
     * @param ResponseInterface|Response $response
     * @param Driver $session
     */
    public function __construct($request, $response, $session = null, $currentRequestId = null)
    {
        $this->request = $request;
        $this->response = $response;
        $this->session = $session;
        $this->currentRequestId = $currentRequestId;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'request';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return [
            "request" => [
                "icon" => "tags",
                "widget" => "PhpDebugBar.Widgets.HtmlVariableListWidget",
                "map" => "request",
                "default" => "{}"
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $request = $this->request;
        $response = $this->response;

        $responseHeaders = $response->getHeaders();
        $cookies = [];
        foreach ($response->getHeaders()['Set-Cookie'] ?? [] as $cookie) {
            $cookies[] = $cookie;
        }
        if (count($cookies) > 0) {
            $responseHeaders['Set-Cookie'] = $cookies;
        }

        $statusCode = $response->getStatusCode();

        $data = [
            'path_info' => $request->getUri()->getPath(),
            'status_code' => $statusCode,
            'status_text' => isset(Response::PHRASES[$statusCode]) ? Response::PHRASES[$statusCode] : '',
            'method' => $request->getMethod(),
            'content_type' => $response->getHeaderLine('Content-Type') ?? 'text/html',
            'request_query' => $request->getQueryParams(),
            'request_request' => $request->getParsedBody(),
            'request_headers' => $request->getHeaders(),
            'request_server' => $request->getServerParams(),
            'request_cookies' => $request->getCookieParams(),
            'response_headers' => $responseHeaders,
        ];

        if (isset($_SESSION)) {
            $data['session_attributes'] = $_SESSION;
        }

        if (isset($data['request_headers']['php-auth-pw'])) {
            $data['request_headers']['php-auth-pw'] = '******';
        }

        if (isset($data['request_server']['PHP_AUTH_PW'])) {
            $data['request_server']['PHP_AUTH_PW'] = '******';
        };

        foreach ($data as $key => $var) {
            if (!is_string($data[$key])) {
                $data[$key] = DataCollector::getDefaultVarDumper()->renderVar($var);
            } else {
                $data[$key] = $var;
            }
        }

        return $data;
    }

    private function getCookieHeader($name, $value, $expires, $path, $domain, $secure, $httponly)
    {
        $cookie = sprintf('%s=%s', $name, urlencode($value));

        if (0 !== $expires) {
            if (is_numeric($expires)) {
                $expires = (int) $expires;
            } elseif ($expires instanceof \DateTime) {
                $expires = $expires->getTimestamp();
            } else {
                $expires = strtotime($expires);
                if (false === $expires || -1 == $expires) {
                    throw new \InvalidArgumentException(
                        sprintf('The "expires" cookie parameter is not valid.', $expires)
                    );
                }
            }

            $cookie .= '; expires=' . substr(
                \DateTime::createFromFormat('U', $expires, new \DateTimeZone('UTC'))->format('D, d-M-Y H:i:s T'),
                0,
                -5
            );
        }

        if ($domain) {
            $cookie .= '; domain=' . $domain;
        }

        $cookie .= '; path=' . $path;

        if ($secure) {
            $cookie .= '; secure';
        }

        if ($httponly) {
            $cookie .= '; httponly';
        }

        return $cookie;
    }
}
