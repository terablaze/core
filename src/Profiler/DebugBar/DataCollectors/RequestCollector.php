<?php

namespace TeraBlaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\RequestDataCollector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\HttpBase\Session\Driver;

class RequestCollector extends RequestDataCollector
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
            if ($this->isHtmlVarDumperUsed()) {
                $data[$key] = $this->getVarDumper()->renderVar($var);
            } else {
                $data[$key] = $this->getDataFormatter()->formatVar($var);
            }
        }

        return $data;
    }
}
