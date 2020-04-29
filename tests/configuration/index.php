<?php
include_once __DIR__ . "/../../vendor/autoload.php";

use TeraBlaze\Configuration\Configuration;
$configuration = new Configuration('phparray');
$configuration = $configuration->initialize();

if ($configuration)
{
    $parsed = $configuration->parse(__DIR__ . "/config");

    if (!empty($parsed->configuration->{'default'}))
    {
        $config = $parsed->configuration->{'default'};
    }
}
print_r($config);
die;