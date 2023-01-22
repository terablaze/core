<?php

namespace Terablaze\Bus;

use DateTimeInterface;

interface PrunableBatchRepositoryInterface extends BatchRepositoryInterface
{
    /**
     * Prune all of the entries older than the given date.
     *
     * @param  \DateTimeInterface  $before
     * @return int
     */
    public function prune(DateTimeInterface $before);
}
