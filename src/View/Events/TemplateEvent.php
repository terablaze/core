<?php

namespace Terablaze\View\Events;

use Terablaze\EventDispatcher\Event;
use Terablaze\View\Template;

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