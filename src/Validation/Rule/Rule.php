<?php

namespace TeraBlaze\Validation\Rule;

abstract class Rule implements RuleInterface
{
    protected string $message;

    abstract public function validate($data, string $field, array $params);

    abstract public function getMessage($data, string $field, array $params);

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
