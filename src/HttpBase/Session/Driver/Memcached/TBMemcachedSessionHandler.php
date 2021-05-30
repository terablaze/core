<?php

namespace TeraBlaze\HttpBase\Session\Driver\Memcached;

class TBMemcachedSessionHandler extends \SessionHandler
{
    public function read($id)
    {
        $data = parent::read($id);
        if (empty($data)) {
            return '';
        } else {
            return $data;
        }
    }
}
