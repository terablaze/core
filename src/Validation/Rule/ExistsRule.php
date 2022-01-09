<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Traits\DatabaseRuleTrait;

class ExistsRule extends Rule implements RuleInterface
{
    use DatabaseRuleTrait;

    public function validate(): bool
    {
        $fieldCount = $this->queryBuilder->select()
            ->from($this->table)
            ->where("$this->column = ?")
            ->setParameters([$this->data[$this->field]])
            ->count();
        return $fieldCount > 0;
    }

    public function getMessage(): string
    {
        return $this->message ?? "The value ('$this->value') submitted for :field must exist in the database";
    }
}
