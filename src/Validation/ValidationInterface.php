<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Support\MessageBag;

interface ValidationInterface
{
    /**
     * Run the validator's rules against its data.
     *
     * @return array
     */
    public function validate(): array;

    /**
     * Get the fields and values that were validated.
     *
     * @param string|null $field
     * @return mixed
     */
    public function validated(string $field = null);

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails(): bool;

    /**
     * Get the failed validation rules.
     *
     * @return array
     */
    public function failed(): array;

    /**
     * Add an after validation callback.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function after($callback): self;

    /**
     * Get the message container for the validator.
     *
     * @return MessageBag
     */
    public function messages(): MessageBag;

    /**
     * An alternative more semantic shortcut to the message container.
     *
     * @return MessageBag
     */
    public function errors(): MessageBag;

    /**
     * Get the messages for the instance.
     *
     * @return MessageBag
     */
    public function getMessageBag(): MessageBag;
}