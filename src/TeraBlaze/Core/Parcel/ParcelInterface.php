<?php

namespace TeraBlaze\Core\Parcel;

use Psr\Container\ContainerInterface;

interface ParcelInterface
{
    public function build(ContainerInterface $container);

}
