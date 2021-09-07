<?php

namespace TeraBlaze\Ripana\Migration\Field;

use TeraBlaze\Ripana\Exception\MigrationException;

class TextField extends Field
{
    public ?string $default = null;

    public function nullable(): self
    {
        throw new MigrationException('Text fields cannot be nullable');
    }

    public function default(string $value): self
    {
        $this->default = $value;
        return $this;
    }
}
