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
}
