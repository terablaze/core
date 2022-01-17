<?php

namespace TeraBlaze\View\Events;

use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\View\Template;

class TemplateEvent extends Event
{
    private Template $template;

    public function __construct(Template $template)
    {
        $this->template = $template;
    }

    /**
     * @return Template
     */
    public function getTemplate(): Template
    {
        return $this->template;
    }
}