<?php

namespace Terablaze\Support\Interfaces;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \Terablaze\Support\Interfaces\MessageBag
     */
    public function getMessageBag();
}
