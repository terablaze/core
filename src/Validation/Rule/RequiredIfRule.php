<?php

namespace TeraBlaze\Validation\Rule;

class RequiredIfRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        $dependant = $this->params[0];
        $dependantValue = $this->params[1];
        if (empty($this->data[$dependant])) {
            return true;
        }
        if ($this->data[$dependant] != $dependantValue) {
            return true;
        }
        return !empty($this->value);
    }

    public function getMessage(): string
    {
        $dependant = $this->params[0];
        $dependantValue = $this->params[1];

        return $this->message ?? ":Field is required if $dependant equals $dependantValue";
    }
}