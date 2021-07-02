<?php

include_once __DIR__ . "/../../vendor/autoload.php";

use TeraBlaze\Config\Configuration;

$configuration = new Configuration('phparray');
$configuration = $configuration->initialize();

if ($configuration) {
    $config = $configuration->parse(__DIR__ . "/config");
    dd($config);
}
die;
