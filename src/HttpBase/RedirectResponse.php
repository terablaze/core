<?php

namespace Terablaze\HttpBase;

use Terablaze\Psr7\Stream;

class RedirectResponse extends Response
{
    protected $targetUrl;
    protected $body;
    protected $headers;

    // Encode <, >, ', &, and " characters in the JSON, making it also safe to be embedded into HTML.
    // 15 === JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    public const DEFAULT_ENCODING_OPTIONS = 15;

    protected $encodingOptions = self::DEFAULT_ENCODING_OPTIONS;


    public function __construct(string $url, int $status = 302)
    {
        $this->setTargetUrl($url);

        if (!in_array($status, [201, 301, 302, 303, 307, 308])) {
            throw new \InvalidArgumentException(
                sprintf('The HTTP status code is not a redirect ("%s" given).', $status)
            );
        }

        if (
            301 == $status && !\array_key_exists(
                'cache-control',
                array_change_key_case($this->headers, \CASE_LOWER)
            )
        ) {
            $this->headers['cache-control'] = null;
        }
        parent::__construct($this->body, $status, $this->headers);
    }

    /**
     * Returns the target URL.
     *
     * @return string target URL
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }

    /**
     * Sets the redirect target of this response.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setTargetUrl(string $url)
    {
        if ('' === $url) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->targetUrl = $url;

        $this->body = sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, \ENT_QUOTES, 'UTF-8'));

        $this->headers['Location'] = $url;

        return $this;
    }
}
