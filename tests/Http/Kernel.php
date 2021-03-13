<?php

namespace Tests\TeraBlaze\Http;

use TeraBlaze\ErrorHandler\HtmlErrorRenderer;

class Kernel extends \TeraBlaze\Core\Kernel\Kernel
{
    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
        HtmlErrorRenderer::setTemplateDir(__DIR__);
    }
}