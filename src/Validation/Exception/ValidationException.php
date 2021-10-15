<?php

namespace TeraBlaze\Validation\Exception;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    protected array $errors = [];
    protected string $sessionName = 'validation_errors';

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setSessionName(string $sessionName): self
    {
        $this->sessionName = $sessionName;
        return $this;
    }

    public function getSessionName(): string
    {
        return $this->sessionName;
    }
}
