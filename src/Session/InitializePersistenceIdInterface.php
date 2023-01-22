<?php

namespace Terablaze\Session;

interface InitializePersistenceIdInterface
{
    /**
     * Returns new instance with id generated / regenerated, if required
     */
    public function initializeId(SessionInterface $session): SessionInterface;
}
