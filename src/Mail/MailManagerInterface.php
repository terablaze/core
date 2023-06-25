<?php

namespace Terablaze\Mail;

interface MailManagerInterface
{
    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return \Terablaze\Mail\MailerInterface
     */
    public function mailer($name = null);
}
