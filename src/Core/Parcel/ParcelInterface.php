<?php

namespace TeraBlaze\Core\Parcel;

use TeraBlaze\Container\ContainerAwareInterface;
use TeraBlaze\Container\ContainerInterface;

interface ParcelInterface extends ContainerAwareInterface
{
    /**
     * Boots the Parcel.
     */
    public function boot(): void;

    /**
     * Shutdowns the Parcel.
     */
    public function shutdown(): void;

    /**
     * Builds the parcel.
     *
     * It is only ever called once when the cache is empty.
     */
    public function build(ContainerInterface $container): void;

    /**
     * Returns the parcel name (the class short name).
     *
     * @return string The Parcel name
     */
    public function getName(): string;

    /**
     * Gets the Parcel namespace.
     *
     * @return string The Parcel namespace
     */
    public function getNamespace(): string;

    /**
     * Gets the Parcel directory path.
     *
     * The path should always be returned as a Unix path (with /).
     *
     * @return string The Parcel absolute path
     */
    public function getPath(): string;
}
