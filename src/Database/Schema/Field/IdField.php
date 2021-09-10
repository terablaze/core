<?php

namespace TeraBlaze\Database\Schema\Field;

use TeraBlaze\Database\Exception\MigrationException;

class IdField extends Field
{
    public function default()
    {
        throw new MigrationException('ID fields cannot have a default value');
    }
}
