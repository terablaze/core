<?php

namespace TeraBlaze\Routing\Generator;

use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\Routing\Exception as Exception;
use TeraBlaze\Routing\Exception\RouteNotFoundException;

/**
 * Interface UrlGeneratorInterface
 * @package TeraBlaze\Routing\Generator
 */
interface UrlGeneratorInterface
{
    /**
     * Generates an absolute URL, e.g. "http://example.com/dir/file".
     */
    public const ABSOLUTE_URL = 0;

    /**
     * Generates an absolute path, e.g. "/dir/file".
     */
    public const ABSOLUTE_PATH = 1;

    /**
     * Generates a relative path based on the current request path, e.g. "../parent-file".
     *
     * @see UrlGenerator::getRelativePath()
     */
    public const RELATIVE_PATH = 2;

    /**
     * Generates a network path, e.g. "//example.com/dir/file".
     * Such reference reuses the current scheme but specifies the host.
     */
    public const NETWORK_PATH = 3;

    /**
     * @param string $name
     * @param array<string, string> $parameters
     * @param int $referenceType
     * @param string|null $locale
     * @return string
     * @throws Exception\MissingParametersException
     * @throws RouteNotFoundException
     * @throws TypeException
     */
    public function generate(
        string $name,
        array $parameters = [],
        int $referenceType = self::ABSOLUTE_PATH,
        ?string $locale = null
    ): string;

    /**
     * @param string $uri
     * @param int $referenceType
     * @return string
     */
    public function generateAsset(string $uri, int $referenceType = self::ABSOLUTE_PATH): string;

    /**
     * Get the current URL for the request.
     *
     * @return string
     */
    public function current(): string;

    /**
     * Get the URL for the previous request.
     *
     * @param  mixed  $fallback
     * @return string
     */
    public function previous($fallback = false): string;

    /**
     * Generate an absolute URL to the given path.
     *
     * @param  string  $path
     * @param  mixed  $extra
     * @param  bool|null  $secure
     * @return string
     */
    public function to($path, $extra = [], $secure = null): string;

    /**
     * Format the given URL segments into a single URL.
     *
     * @param  string  $root
     * @param  string  $path
     * @return string
     */
    public function format($root, $path): string;

    /**
     * Get the default scheme for a raw URL.
     *
     * @param  bool|null  $secure
     * @return string
     */
    public function formatScheme($secure = null): string;

    /**
     * Get the base URL for the request.
     *
     * @param  string  $scheme
     * @param  string|null  $root
     * @return string
     */
    public function formatRoot($scheme, $root = null): string;
}
