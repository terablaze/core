<?php

declare(strict_types=1);

namespace Terablaze\Session\Flash;

use Terablaze\Session\Exception\InvalidFlashMessagesImplementationException;
use Terablaze\Session\Exception\MissingSessionException;
use Terablaze\Session\SessionInterface;
use Terablaze\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function class_exists;
use function class_implements;
use function in_array;

class FlashMessageMiddleware implements MiddlewareInterface
{
    public const FLASH_ATTRIBUTE = 'flash';

    /** @var string */
    private $attributeKey;

    /** @var callable */
    private $flashMessageFactory;

    /** @var string */
    private $sessionKey;

    public function __construct(
        string $flashMessagesClass = FlashMessages::class,
        string $sessionKey = FlashMessagesInterface::FLASH_NEXT,
        string $attributeKey = self::FLASH_ATTRIBUTE
    ) {
        if (
            ! class_exists($flashMessagesClass)
            || ! in_array(FlashMessagesInterface::class, class_implements($flashMessagesClass), true)
        ) {
            throw InvalidFlashMessagesImplementationException::forClass($flashMessagesClass);
        }

        $this->flashMessageFactory = [$flashMessagesClass, 'createFromSession'];
        $this->sessionKey          = $sessionKey;
        $this->attributeKey        = $attributeKey;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE, false);
        if (! $session instanceof SessionInterface) {
            throw MissingSessionException::forFlashMiddleware($this);
        }

        $flashMessages = ($this->flashMessageFactory)($session, $this->sessionKey);

        return $handler->handle(
            $request
                ->withAttribute($this->attributeKey, $flashMessages)
                ->withAttribute(FlashMessagesInterface::class, $flashMessages)
        );
    }
}
