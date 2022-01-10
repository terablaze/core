<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Validation\Rule\Builder\UniqueRuleBuilder;
use TeraBlaze\Validation\Validation;

abstract class Rule implements RuleInterface
{
    protected Validation $validation;
    protected string $field;

    /** @var mixed $value */
    protected $value;

    /** @var array<string, mixed> $data */
    protected array $data;

    /** @var array<int, mixed> $params */
    protected array $params;

    protected ?string $message = null;

    /**
     * @param string $field
     * @param array<string, mixed> $data
     * @param mixed|array<int, mixed> $params
     */
    public function __construct(Validation $validation, string $field, array $data = [], $params = [])
    {
        $this->validation = $validation;
        $this->field = $field;
        $this->value = ArrayMethods::get($data, $field);
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

    /**
     * Check if the parameters are of the same type.
     *
     * @param  mixed  $first
     * @param  mixed  $second
     * @return bool
     */
    protected function isSameType($first, $second)
    {
        return gettype($first) == gettype($second);
    }
}
