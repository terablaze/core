<?php

namespace TeraBlaze\Ripana\Database\Drivers\Mysqli;

use TeraBlaze\Ripana\Database\Exception as Exception;
use TeraBlaze\Ripana\Database\Query as BaseQuery;
use TeraBlaze\Ripana\Database\QueryInterface;

class Query extends BaseQuery implements QueryInterface
{
    public function all(): array
    {
        $sql = $this->_buildSelect();
        /** @var \mysqli_result $result */
        $result = $this->connector->execute($sql, $this->_dumpSql);

        if ($result === false) {
            $error = $this->connector->lastError;
            throw new Exception\Sql("There was an error with your SQL query: {$error} in \n {$sql}");
        }

        $rows = [];

        for ($i = 0; $i < $result->num_rows; $i++) {
            $rows[] = $result->fetch_array(MYSQLI_ASSOC);
        }

        return $rows;
    }
}
