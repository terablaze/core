<?php

namespace TeraBlaze\Queue\Queues;

interface QueueInterface
{
    public function push(callable $closure, ...$params): int;

    public function shift(): ?Job;
}
