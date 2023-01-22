<?php

declare(strict_types=1);

namespace Terablaze\Session\Flash;

use Terablaze\Session\SessionInterface;

interface FlashMessagesInterface
{
    /**
     * Flash values scheduled for next request.
     */
    public const FLASH_NEXT = self::class . '::FLASH_NEXT';

    /**
     * Create an instance from a session container.
     *
     * Flash messages will be retrieved from and persisted to the session via
     * the `$sessionKey`.
     */
    public static function createFromSession(
        SessionInterface $session,
        string $sessionKey = self::FLASH_NEXT
    ): FlashMessagesInterface;

    /**
     * Set a flash value with the given key.
     *
     * Flash values are accessible on the next "hop", where a hop is the
     * next time the session is accessed; you may pass an additional $hops
     * integer to allow access for more than one hop.
     *
     * @param mixed $value
     */
    public function flash(string $key, $value, int $hops = 1): void;

    /**
     * Set a flash value with the given key, but allow access during this request.
     *
     * Flash values are generally accessible only on subsequent requests;
     * using this method, you may make the value available during the current
     * request as well.
     *
     * @param mixed $value
     */
    public function flashNow(string $key, $value, int $hops = 1): void;

    /**
     * Retrieve a flash value.
     *
     * Will return a value only if a flash value was set in a previous request,
     * or if `flashNow()` was called in this request with the same `$key`.
     *
     * WILL NOT return a value if set in the current request via `flash()`.
     *
     * @param mixed $default Default value to return if no flash value exists.
     * @return mixed
     */
    public function getFlash(string $key, $default = null);

    /**
     * Retrieve all flash values.
     *
     * Will return all values was set in a previous request, or if `flashNow()`
     * was called in this request.
     *
     * WILL NOT return values set in the current request via `flash()`.
     *
     * @return array
     */
    public function getFlashes(): array;

    /**
     * Clear all flash values.
     *
     * Affects the next and subsequent requests.
     */
    public function clearFlash(): void;

    /**
     * Prolongs any current flash messages for one more hop.
     */
    public function prolongFlash(): void;

    /**
     * Re-flashes any current flash messages for one additional hop.
     */
    public function reflash(): void;
}
