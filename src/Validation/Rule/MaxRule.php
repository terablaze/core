<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use TeraBlaze\Support\StringMethods;

class MaxRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a max size/length');
        }

        $max = (int) $this->params[0];
        return $this->getSize() <= $max;
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return $this->message ??
            trim(":field should {$this->messageModifier['presence']} at most $length {$this->messageModifier['unit']}");
    }
}
