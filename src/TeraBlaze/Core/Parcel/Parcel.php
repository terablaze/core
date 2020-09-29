<?php

namespace TeraBlaze\Core\Parcel;

use Psr\Container\ContainerInterface;

abstract class Parcel implements ParcelInterface
{
    public abstract function build(ContainerInterface $container);
}
