<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Builder\UniqueRuleBuilder;

abstract class Rule implements RuleInterface
{
    protected string $field;

    /** @var mixed $value */
    protected $value;

    /** @var array<string, mixed> $data */
    protected array $data;

    /** @var array<string, mixed> $params */
    protected array $params;

    protected ?string $message = null;

    /**
     * @param string $field
     * @param array<string, mixed> $data
     * @param mixed|array<string, mixed> $params
     */
    public function __construct(string $field, array $data = [], $params = [])
    {
        $this->field = $field;
        $this->value = $data[$field] ?? null;
        $this->data = $data;
        $this->params = $params;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public static function unique(string $table, string $column = 'NULL')
    {
        return new UniqueRuleBuilder($table, $column);
    }
}
