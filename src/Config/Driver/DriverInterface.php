<?php

namespace TeraBlaze\Config\Driver;

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
     * @return array<string, mixed>
     */
    public function parseArray(string $path): array;
}
