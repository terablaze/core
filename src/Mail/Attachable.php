<?php

namespace Terablaze\Mail;

interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     * @return \Terablaze\Mail\Attachment
     */
    public function toMailAttachment();
}
