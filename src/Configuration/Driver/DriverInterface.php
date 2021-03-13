<?php

namespace TeraBlaze\Configuration\Driver;

/**
 * Class Driver
 * @package TeraBlaze\Configuration
 */
interface DriverInterface
{
    /**
     * Undocumented function
     *
     * @param string $path
     * @return object|null
     */
	public function parse(string $path): ?object;

    /**
     * Undocumented function
     *
     * @param string $path
     * @return array|null
     */
	public function parseArray(string $path): ?array;
}
