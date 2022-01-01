<?php

namespace TeraBlaze\Validation\Rule;

class UniqueRule extends Rule implements RuleInterface
{
    use DatabaseRuleTrait;

    public function validate(): bool
    {
        $skipValue = $this->params[2] ?? "NULL";
        $skipColumn = $this->params[3] ?? 'id';

        $parameters = [$this->data[$this->field]];
        $query = $this->queryBuilder->select()
            ->from($this->table)
            ->where("$this->column = ?");
        if ($skipValue !== "NULL") {
            $query->andWhere("$skipColumn != ?");
            $parameters[] = $skipValue;
        }
        $query->setParameters($parameters);
        $fieldCount = $query->count();
        return $fieldCount == 0;
    }

    public function getMessage(): string
    {
        return $this->message ?? "The value ('$this->value') submitted for :field must be unique in the database";
    }
}
