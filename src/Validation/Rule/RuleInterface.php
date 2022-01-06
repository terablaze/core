<?php

namespace TeraBlaze\Validation\Rule;

interface RuleInterface
{
    public function setMessage(string $message): self;

    public function setData(array $data): self;

    public function getMessage(): string;

    public function validate(): bool;
}
