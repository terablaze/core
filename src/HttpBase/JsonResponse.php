<?php

namespace TeraBlaze\HttpBase;

use TeraBlaze\Psr7\Stream;

class JsonResponse extends Response
{
    protected $data;
    protected $statusCode = self::HTTP_OK;
    protected $headers = [];
    protected $callback;

    // Encode <, >, ', &, and " characters in the JSON, making it also safe to be embedded into HTML.
    // 15 === JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    public const DEFAULT_ENCODING_OPTIONS = 15;

    protected $encodingOptions = self::DEFAULT_ENCODING_OPTIONS;

    /**
     * @param mixed $body
     * @param int $status
     * @param array $headers
     * @param bool $json
     */
    public function __construct($body = null, int $status = self::HTTP_OK, array $headers = [], bool $json = null)
    {
        if ($json && !\is_string($body) && !is_numeric($body) && !\is_callable([$body, '__toString'])) {
            throw new \TypeError(sprintf('"%s": If $json is set to true, argument $data must be a string ' .
                'or object implementing __toString(), "%s" given.', __METHOD__, get_debug_type($body)));
        }

        if (null === $body) {
            $body = new \ArrayObject();
        }

        $json ? $this->setJson($body) : $this->setData($body);
        parent::__construct($this->data, $this->statusCode, $this->headers);
    }

    /**
     * Sets a raw string containing a JSON document to be sent.
     *
     * @return $this
     */
    public function setJson(string $json)
    {
        $this->data = $json;

        return $this->update();
    }

    /**
     * Sets the data to be sent as JSON.
     *
     * @param mixed $data
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setData($data = [])
    {
        $data = jsonEncode($data, $this->encodingOptions);

        if (\PHP_VERSION_ID >= 70300 && (\JSON_THROW_ON_ERROR & $this->encodingOptions)) {
            return $this->setJson($data);
        }

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        return $this->setJson($data);
    }

    /**
     * Returns options used while encoding data to JSON.
     *
     * @return int
     */
    public function getEncodingOptions()
    {
        return $this->encodingOptions;
    }

    /**
     * Sets options used while encoding data to JSON.
     *
     * @return $this
     */
    public function setEncodingOptions(int $encodingOptions)
    {
        $this->encodingOptions = $encodingOptions;

        return $this->setData(jsonDecode($this->data));
    }

    protected function update()
    {
        if (null !== $this->callback) {
            // Not using application/javascript for compatibility reasons with older browsers.
            $this->headers['Content-Type'] = 'text/javascript';

            $this->data = sprintf(
                '/**/%s(%s);',
                $this->callback,
                $this->data
            );
            return $this->withBody(Stream::create($this->data));
        }

        // Only set the header when there is none or when it equals 'text/javascript' (from a previous update with callback)
        // in order to not overwrite a custom definition.
        if (
            !$this->hasHeader('Content-Type') ||
            'text/javascript' === $this->getHeader('Content-Type') ||
            !array_key_exists('Content-Type', $this->headers) ||
            'text/javascript' === $this->headers['Content-Type']
        ) {
            $this->headers['Content-Type'] = 'application/json';
        }

        return $this->withBody(Stream::create($this->data));
    }
}
