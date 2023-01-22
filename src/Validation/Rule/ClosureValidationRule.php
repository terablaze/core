<?php

namespace Terablaze\Validation\Rule;


use Terablaze\Validation\Validation;

class ClosureValidationRule extends Rule implements RuleInterface
{
    /**
     * The callback that validates the field.
     *
     * @var \Closure
     */
    public $callback;

    /**
     * Indicates if the validation callback failed.
     *
     * @var bool
     */
    public $failed = false;

    public function __construct(Validation $validation, \Closure $rule, string $field, array $data = [], $params = [])
    {
        parent::__construct($validation, $field, $data, $params);
        $this->callback = $rule;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return bool
     */
    public function validate(): bool
    {
        $this->failed = false;

        $this->callback->__invoke($this->field, $this->value, function ($message) {
            $this->failed = true;

            $this->message = $message;
        });

        return ! $this->failed;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
