<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/1/2017
 * Time: 9:19 AM
 */

namespace TeraBlaze\Ripana\Database\Query;

use TeraBlaze\Ripana\Database\Exception as Exception;

/**
 * Class Mysql
 * @package TeraBlaze\Ripana\Database\Query
 */
class Mysql extends Query
{
	
	/**
	 * @return array
	 * @throws Exception\Sql
	 */
	public function all(): array
	{
		$sql = $this->_buildSelect();
		$result = $this->connector->execute($sql);
		
		if ($result === false) {
			$error = $this->connector->lastError;
			throw new Exception\Sql("There was an error with your SQL query: {$error}");
		}
		
		$rows = array();
		
		for ($i = 0; $i < $result->num_rows; $i++) {
			$rows[] = $result->fetch_array(MYSQLI_ASSOC);
		}
		
		return $rows;
	}
}