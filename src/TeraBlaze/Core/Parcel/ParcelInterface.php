<?php

namespace TeraBlaze\Core\Parcel;

use TeraBlaze\Container\ContainerInterface;

interface ParcelInterface
{
    public function build(ContainerInterface $container);
}
