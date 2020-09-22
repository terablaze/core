<?php
include_once __DIR__ . "/../../vendor/autoload.php";

use TeraBlaze\Configuration\Configuration;

$configuration = new Configuration('phparray');
$configuration = $configuration->initialize();

if ($configuration) {
    $config = $configuration->parse(__DIR__ . "/config");
    dd($config);
}
die;
