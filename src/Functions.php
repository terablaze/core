<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Core\Exception\JsonDecodeException;
use TeraBlaze\Core\Exception\JsonEncodeException;

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

function jsonDecode($json, $assoc = false, $depth = 512, $options = 0)
{
    $ret = json_decode($json, $assoc, $depth, $options);
    if ($error = json_last_error())
    {
        throw new JsonDecodeException(json_last_error_msg(), $error);
    }
    return $ret;
}

function jsonEncode($value, $flags = 0, $depth = 512): string
{
    $ret = json_encode($value, $flags, $depth);
    if ($error = json_last_error())
    {
        throw new JsonEncodeException(json_last_error_msg(), $error);
    }
    return $ret;
}