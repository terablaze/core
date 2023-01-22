<?php

namespace Terablaze\Support\Interfaces;

use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;

interface Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param Request $request
     * @return Response
     */
    public function toResponse(Request $request): Response;
}
