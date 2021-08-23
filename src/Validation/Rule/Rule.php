<?php

namespace TeraBlaze\Validation\Rule;

abstract class Rule implements RuleInterface
{
    protected string $message;

    public abstract function validate(array $data, string $field, array $params);

    public abstract function getMessage(array $data, string $field, array $params);

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
