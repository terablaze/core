<?php

namespace TeraBlaze\Session;

use TeraBlaze\Session\Csrf\CsrfGuardInterface;
use TeraBlaze\Session\Flash\FlashMessagesInterface;

interface SessionInterface
{
    /**
     * Serialize the session data to an array for storage purposes.
     */
    public function toArray(): array;

    /**
     * Retrieve a value from the session.
     *
     * @param null|mixed $default Default value to return if $name does not exist.
     * @return mixed
     */
    public function get(string $name, $default = null);

    /**
     * Whether or not the container has the given key.
     */
    public function has(string $name): bool;

    /**
     * Set a value within the session.
     *
     * Values MUST be serializable in any format; we recommend ensuring the
     * values are JSON serializable for greatest portability.
     *
     * @param mixed $value
     */
    public function set(string $name, $value): void;

    /**
     * Remove a value from the session.
     */
    public function unset(string $name): void;

    /**
     * Clear all values.
     */
    public function clear(): void;

    /**
     * Does the session contain changes? If not, the middleware handling
     * session persistence may not need to do more work.
     */
    public function hasChanged(): bool;

    /**
     * Regenerate the session.
     *
     * This can be done to prevent session fixation. When executed, it SHOULD
     * return a new instance; that instance should always return true for
     * isRegenerated().
     *
     * An example of where this WOULD NOT return a new instance is within the
     * shipped LazySession, where instead it would return itself, after
     * internally re-setting the proxied session.
     */
    public function regenerate(): self;

    /**
     * Method to determine if the session was regenerated; should return
     * true if the instance was produced via regenerate().
     */
    public function isRegenerated(): bool;

    public function getFlash(): FlashMessagesInterface;

    public function getCsrf(): CsrfGuardInterface;

    /**
     * Flash an input array to the session.
     *
     * @param  array  $value
     * @return void
     */
    public function flashInput(array $value): void;

    /**
     * Determine if the session contains old input.
     *
     * @param  string|null  $key
     * @return bool
     */
    public function hasOldInput($key = null): bool;

    /**
     * Get the requested item from the flashed input array.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getOldInput($key = null, $default = null);
}
