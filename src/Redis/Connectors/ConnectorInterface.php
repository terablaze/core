<?php

namespace Terablaze\Redis\Connectors;

interface ConnectorInterface
{
    /**
     * Create a connection to a Redis cluster.
     *
     * @param  array  $config
     * @param  array  $options
     * @return \Terablaze\Redis\Connections\Connection
     */
    public function connect(array $config, array $options);

    /**
     * Create a connection to a Redis instance.
     *
     * @param  array  $config
     * @param  array  $clusterOptions
     * @param  array  $options
     * @return \Terablaze\Redis\Connections\Connection
     */
    public function connectToCluster(array $config, array $clusterOptions, array $options);
}
