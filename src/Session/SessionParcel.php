<?php

namespace Terablaze\Session;

use InvalidArgumentException;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Session\Csrf\CsrfGuardInterface;
use Terablaze\Session\Csrf\CsrfMiddleware;
use Terablaze\Session\Csrf\FlashCsrfGuard;
use Terablaze\Session\Csrf\SessionCsrfGuard;
use Terablaze\Session\Flash\FlashMessageMiddleware;
use Terablaze\Session\Flash\FlashMessagesInterface;
use Terablaze\Session\Persistence\CacheSessionPersistence;
use Terablaze\Session\Persistence\PhpSessionPersistence;
use Terablaze\Session\Persistence\SessionPersistenceInterface;
use Terablaze\Support\StringMethods;

class SessionParcel extends Parcel
{
    public function boot(): void
    {
        $config = $this->loadConfig("session");
        $this->initPersistence($config);
    }

    private function initPersistence($config)
    {
        $sessionPersistenceName = 'session.persistence';

        switch ($driver = $config->get('session.driver')) {
            case "ext":
            case "php":
                $sessionPersistence = new PhpSessionPersistence(
                    $config->get('non_locking', false),
                    $config->get('non_locking', false)
                );
                break;
            case "cache":
                $sessionCache = 'simple_cache.' . $config->get('session.cache');
                if (!$this->container->has($sessionCache)) {
                    throw new InvalidArgumentException(
                        "If you're using a cache persistence, you must add it to your cache config"
                    );
                }
                $sessionPersistence = new CacheSessionPersistence(
                    $this->container->get($sessionCache),
                    $config->get(
                        'session.cookie.name',
                        StringMethods::slug(getConfig('app.name', 'terablaze'), '_') . '_session'
                    ),
                    $config->get('session.cookie.path', '/'),
                    $config->get('session.limiter', 'nocache'),
                    $config->get('session.expire', 10800),
                    $config->get('session.last_modified', null),
                    $config->get('session.persistent', false),
                    $config->get('session.cookie.domain', null),
                    $config->get('session.cookie.secure', false),
                    $config->get('session.cookie.http_only', false),
                    $config->get('session.cookie.same_site', 'Lax')
                );
                break;
            default:
                throw new InvalidArgumentException(sprintf("Invalid or unimplemented session type: %s", $driver));
        }
        $this->container->registerServiceInstance($sessionPersistenceName, $sessionPersistence);
        $this->container->setAlias(SessionPersistenceInterface::class, $sessionPersistenceName);

        $this->container->make(SessionMiddleware::class);
        $this->container->make(FlashMessageMiddleware::class);
        $this->container->make(CsrfMiddleware::class, [
            'arguments' => [$config->get('session.csrf_guard', '')]
        ]);
        $this->getKernel()->registerMiddleWare(SessionMiddleware::class);
        $this->getKernel()->registerMiddleWare(FlashMessageMiddleware::class);
        $this->getKernel()->registerMiddleWare(CsrfMiddleware::class);
    }
}
