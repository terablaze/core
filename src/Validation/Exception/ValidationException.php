<?php

namespace TeraBlaze\Validation\Exception;

use Exception;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Validation\Validation;

class ValidationException extends Exception
{
    public Validation $validation;

    /**
     * The recommended response to send to the client.
     *
     * @var Response|null
     */
    public ?Response $response = null;

    /**
     * The status code to use for the response.
     *
     * @var int
     */
    public $status = 422;

    /**
     * The path the client should be redirected to.
     *
     * @var string
     */
    public $redirectTo;

    /**
     * Create a new exception instance.
     *
     * @param  Validation  $validation
     * @param  Response|null  $response
     * @return void
     */
    public function __construct($validation, $response = null)
    {
        parent::__construct('The given data was invalid.');

        $this->validation = $validation;
        $this->response = $response;
    }

    /**
     * Get all of the validation error messages.
     *
     * @return array
     */
    public function errors()
    {
        return $this->validation->messages()->messages();
    }

    /**
     * Set the HTTP status code to be used for the response.
     *
     * @param  int  $status
     * @return $this
     */
    public function status($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Set the URL to redirect to on a validation error.
     *
     * @param  string  $url
     * @return $this
     */
    public function redirectTo($url)
    {
        $this->redirectTo = $url;

        return $this;
    }

    /**
     * Get the underlying response instance.
     *
     * @return Response|null
     */
    public function getResponse()
    {
        return $this->response;
    }
}
