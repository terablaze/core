<?php

namespace TeraBlaze\Core\Kernel\EventListeners;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Sets the session in the request.
 *
 * When the passed container contains a "session_storage" entry which
 * holds a NativeSessionStorage instance, the "cookie_secure" option
 * will be set to true whenever the current master request is secure.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class SessionListener extends AbstractSessionListener
{
    public function __construct(ContainerInterface $container, bool $debug = false)
    {
        parent::__construct($container, $debug);
    }

    protected function getSession(): ?SessionInterface
    {
        if (!$this->container->has('session')) {
            return null;
        }

        if (
            $this->container->has('session_storage')
            && ($storage = $this->container->get('session_storage')) instanceof NativeSessionStorage
            && ($masterRequest = $this->container->get('request_stack')->getMasterRequest())
            && $masterRequest->isSecure()
        ) {
            $storage->setOptions(['cookie_secure' => true]);
        }

        return $this->container->get('session');
    }
}
