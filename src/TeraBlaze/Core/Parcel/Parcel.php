<?php

namespace TeraBlaze\Core\Parcel;

use Psr\Container\ContainerInterface;

abstract class Parcel implements ParcelInterface
{
    public function build(ContainerInterface $container)
    {

    }
}
