<?php

namespace TeraBlaze\Interfaces\Support;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \TeraBlaze\Interfaces\Support\MessageBag
     */
    public function getMessageBag();
}
