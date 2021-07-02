<?php

namespace Tests\TeraBlaze\Http;

use TeraBlaze\ErrorHandler\HtmlErrorRenderer;

class Kernel extends \TeraBlaze\Core\Kernel\Kernel
{
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        HtmlErrorRenderer::setTemplateDir(__DIR__);
    }
}
