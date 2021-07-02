<?php

namespace TeraBlaze\Ripana\Database\Query;

use TeraBlaze\Ripana\Database\Exception\ServiceException;
use TeraBlaze\Ripana\Database\Exception\SqlException;

class MysqliQuery extends Query implements QueryInterface
{
    /**
     * Returns all matched rows
     *
     * @return array<string|int, mixed>
     * @throws SqlException
     * @throws ServiceException
     */
    public function all(): array
    {
        $sql = $this->_buildSelect();
        $result = $this->connector->execute($sql, $this->dumpSql);

        if ($result === false) {
            $error = $this->connector->getLastError();
            throw new SqlException("There was an error with your SQL query: {$error} in \n {$sql}");
        }

        $rows = [];

        for ($i = 0; $i < $result->num_rows; $i++) {
            $rows[] = $result->fetch_array(MYSQLI_ASSOC);
        }

        return $rows;
    }
}
