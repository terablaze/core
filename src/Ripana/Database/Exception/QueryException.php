<?php

namespace TeraBlaze\Ripana\Database\Exception;

use PDOException;
use Throwable;

class QueryException extends PDOException
{
    /**
     * @param string   $alias
     * @param string[] $registeredAliases
     *
     * @return QueryException
     */
    public static function unknownAlias($alias, $registeredAliases)
    {
        return new self("The given alias '" . $alias . "' is not part of " .
            'any FROM or JOIN clause table. The currently registered ' .
            'aliases are: ' . implode(', ', $registeredAliases) . '.');
    }

    /**
     * @param string   $alias
     * @param string[] $registeredAliases
     *
     * @return QueryException
     */
    public static function nonUniqueAlias($alias, $registeredAliases)
    {
        return new self("The given alias '" . $alias . "' is not unique " .
            'in FROM and JOIN clause table. The currently registered ' .
            'aliases are: ' . implode(', ', $registeredAliases) . '.');
    }

    /**
     * @param Throwable $driverEx
     * @param $sql
     * @param array $params
     * @return QueryException
     */
    public static function driverExceptionDuringQuery(Throwable $driverEx, $sql, array $params = [])
    {
        $msg = "An exception occurred while executing '" . $sql . "'";
        if ($params) {
            $msg .= ' with params ' . self::formatParameters($params);
        }

        $msg .= ":\n\n" . $driverEx->getMessage();

        return static::wrapException($driverEx, $msg);
    }

    /**
     * @return QueryException
     */
    private static function wrapException(Throwable $driverEx, string $msg)
    {
        return new QueryException($msg, 0, $driverEx);
    }

    /**
     * Returns a human-readable representation of an array of parameters.
     * This properly handles binary data by returning a hex representation.
     *
     * @param mixed[] $params
     *
     * @return string
     */
    private static function formatParameters(array $params)
    {
        return '[' . implode(', ', array_map(static function ($param) {
                if (is_resource($param)) {
                    return (string) $param;
                }

                $json = @json_encode($param);

                if (! is_string($json) || $json === 'null' && is_string($param)) {
                    // JSON encoding failed, this is not a UTF-8 string.
                    return sprintf('"%s"', preg_replace('/.{2}/', '\\x$0', bin2hex($param)));
                }

                return $json;
            }, $params)) . ']';
    }
}
