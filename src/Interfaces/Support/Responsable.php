<?php

namespace TeraBlaze\Interfaces\Support;

use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

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
