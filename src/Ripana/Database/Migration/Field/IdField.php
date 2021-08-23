<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

use TeraBlaze\Ripana\Exception\MigrationException;

class IdField extends Field
{
    public function default()
    {
        throw new MigrationException('ID fields cannot have a default value');
    }
}
