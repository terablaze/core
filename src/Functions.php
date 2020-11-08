<?php

use TeraBlaze\Container\Container;

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 3/20/2017
 * Time: 11:22 AM
 */

function makeDir($dir, $recursive = TRUE, $permissions = 0777)
{
    if (!is_dir($dir)) {
        return mkdir($dir, $permissions, $recursive);
    } else {
        return $dir;
    }
}

function get_config($key)
{
    $container = Container::getContainer();
    $config = $container->getParameter('config');
    return $config->$key ?? null;
}
