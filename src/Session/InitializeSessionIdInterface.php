<?php

namespace Terablaze\Session;

interface InitializeSessionIdInterface
{
    /**
     * Returns id of session, generating / regenerating if required
     */
    public function initializeId(): string;
}
