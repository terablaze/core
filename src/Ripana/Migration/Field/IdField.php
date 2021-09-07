<?php

namespace TeraBlaze\Ripana\Migration\Field;

use TeraBlaze\Ripana\Exception\MigrationException;

class IdField extends Field
{
    public function default()
    {
        throw new MigrationException('ID fields cannot have a default value');
    }
}
