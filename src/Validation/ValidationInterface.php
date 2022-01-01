<?php

namespace TeraBlaze\Validation;

interface ValidationInterface
{
    /**
     * Run the validator's rules against its data.
     *
     * @return array
     */
    public function validate(): array;

    /**
     * Get the attributes and values that were validated.
     *
     * @return array
     */
    public function validated(): array;

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
     * Get all of the validation error messages.
     *
     * @return array<string, mixed>
     */
    public function errors(): array;
}