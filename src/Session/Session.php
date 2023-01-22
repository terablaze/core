<?php

namespace Terablaze\Session;

use stdClass;
use Terablaze\Session\Csrf\CsrfGuardInterface;
use Terablaze\Session\Flash\FlashMessageMiddleware;
use Terablaze\Session\Flash\FlashMessagesInterface;

use Terablaze\Support\ArrayMethods;
use function array_key_exists;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;

class Session implements
    SessionCookiePersistenceInterface,
    SessionIdentifierAwareInterface,
    SessionInterface
{
    /**
     * Current data within the session.
     *
     * @var array<int|string, mixed>
     */
    private array $data;

    /**
     * The session identifier, if any.
     *
     * This is present in the session to allow the session persistence
     * implementation to be stateless. When present here, we can query for it
     * when it is time to persist the session, instead of relying on state in
     * the persistence instance (which may be shared between multiple
     * requests).
     */
    private string $id;

    private bool $isRegenerated = false;

    /**
     * Original data provided to the constructor.
     *
     * @var array<int|string, mixed>
     */
    private array $originalData;

    /**
     * Lifetime of the session cookie.
     *
     * @var int
     */
    private $sessionLifetime = 0;

    public function __construct(array $data, string $id = '')
    {
        $this->data = $this->originalData = $data;
        $this->id = $id;

        if (isset($data[SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY])) {
            $this->sessionLifetime = $data[SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY];
        }
    }

    /**
     * Convert a value to a JSON-serializable value.
     *
     * This value should be used by `set()` operations to ensure that the values
     * within a session are serializable across any session adapter.
     *
     * @param mixed $value
     * @return null|bool|int|float|string|array|stdClass
     */
    public static function extractSerializableValue($value)
    {
        return json_decode(json_encode($value, JSON_PRESERVE_ZERO_FRACTION), true);
    }

    /**
     * Retrieve all data for purposes of persistence.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param null|mixed $default Default value to return if $name does not exist.
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        return $this->data[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
        $this->data[$name] = self::extractSerializableValue($value);
    }

    public function unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function hasChanged(): bool
    {
        if ($this->isRegenerated) {
            return true;
        }

        return $this->data !== $this->originalData;
    }

    public function regenerate(): SessionInterface
    {
        $session = clone $this;
        $session->isRegenerated = true;
        return $session;
    }

    public function isRegenerated(): bool
    {
        return $this->isRegenerated;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function persistSessionFor(int $duration): void
    {
        $this->sessionLifetime = $duration;
        $this->set(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY, $duration);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function getSessionLifetime(): int
    {
        return $this->sessionLifetime;
    }

    public function getFlash(): FlashMessagesInterface
    {
        return request()->getFlash();
    }

    public function getCsrf(): CsrfGuardInterface
    {
        return request()->getCsrf();
    }

    public function flashInput(array $value): void
    {
        $this->getFlash()->flash('_old_input', $value);
    }

    /**
     * Determine if the session contains old input.
     *
     * @param  string|null  $key
     * @return bool
     */
    public function hasOldInput($key = null): bool
    {
        $old = $this->getOldInput($key);

        return is_null($key) ? count($old) > 0 : ! is_null($old);
    }

    /**
     * Get the requested item from the flashed input array.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getOldInput($key = null, $default = null)
    {
        return ArrayMethods::get($this->getFlash()->getFlash('_old_input', []), $key, $default);
    }

    /**
     * Determine if the session contains validation error.
     *
     * @param  string|null  $key
     * @return bool
     */
    public function hasValidationError($key = null): bool
    {
        $old = $this->getValidationError($key);

        return is_null($key) ? count($old) > 0 : ! is_null($old);
    }

    /**
     * Get the requested item from the flashed validation errors.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getValidationError($key = null, $default = null)
    {
        return ArrayMethods::get($this->getFlash()->getFlash('_validation_errors', []), $key, $default);
    }
}
