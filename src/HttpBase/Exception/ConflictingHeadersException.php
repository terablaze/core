<?php

namespace TeraBlaze\HttpBase\Exception;

/**
 * The HTTP request contains headers with conflicting information.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class ConflictingHeadersException extends \UnexpectedValueException implements RequestExceptionInterface
{
}
