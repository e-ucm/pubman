<?php

namespace es\eucm;

class MySQLStatementTemplate {

	private $conn;


	public function __construct(\mysqli $conn) {
		$this->conn = $conn;
	}

	public function setConnection(\mysqli $conn) {
		if ($conn === NULL) {
			throw new Exception('$conn must not be null.');
		}
		$this->conn = $conn;
	}

	public function execute($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		if (!$this->conn->query($query)) {
			$this->notifyError($query);
		}
	}

	public function queryAllRows($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		$rs = $this->conn->query($query);
		if (!$rs) {
			$this->notifyError($query);
		}
		return $rs->fetch_all(\mysqli::MYSQLI_ASSOC);
	}

	public function query($query, $processRow, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		$rs = $this->conn->query($query);
		if (!$rs) {
			$this->notifyError($query);
		}
		$results = array();
		while ($row = $rs->fetch_assoc()) {
			$results[] = \call_user_func($processRow, $row);
		}
		return $results;
	}

	public function queryUnique($query, $processRow, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		$rs = $this->conn->query($query);
		if (!$rs) {
			$this->notifyError($query);
		}
		$result = FALSE;
		if ($row = $rs->fetch_assoc()) {
			$result = \call_user_func($processRow, $row);
		}
		return $result;
	}

	public function insert($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		if (!$this->conn->query($query)) {
			$this->notifyError($query);
		}
		return $this->conn->affected_rows;
	}

	public function insertReturnLastId($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		if (!$this->conn->query($query)) {
			$this->notifyError($query);
		}
		return $this->conn->insert_id;
	}

	public function update($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		if (!$this->conn->query($query)) {
			$this->notifyError($query);
		}
		return $this->conn->affected_rows;
	}

	public function delete($query, array $params = NULL) {
		$query = $this->bindParams($query, $params);
		if (!$this->conn->query($query)) {
			$this->notifyError($query);
		}
		return $this->conn->affected_rows;
	}

	private function notifyError($query) {
		throw new \Exception('Error executing statement: '.$query.' error: '.$this->conn->error, $this->conn->errno);
	}

	private function bindParams($query, $params){
		$result = $query;
		if ($params !== NULL) {
			$paramsCount = count($params);
			if ($paramsCount < 2) {
				throw new \Exception('2 parameters are expected at least for the SQL parameters');
			}
			if (strlen($params[0]) === ($paramsCount - 1 )) {
			}
			$paramsCount = strlen($params[0]);
			$paramsTypes = $params[0];
			
			$chunks = explode('?', $query);

			$chunksCount = count($chunks);

			if ( $chunksCount !== ($paramsCount+ 1)) {
				throw new \Exception('Number of provided params is different than expected.');
			}
			$result ='';
			for($i=0; $i < $paramsCount; $i++) {
				$result .= $chunks[$i];
				switch($paramsTypes[$i]) {
					case 'i' :
						$param=$params[$i+1];
						if (!is_int($param) && !is_null($param)) {
							throw new \Exception('Parameter: '.strval($i).' must have integer type');
						}
						$result .= strval($params[$i+1]);
					break;
					case 's' :
						$param=$params[$i+1];
						if (!is_string($param) && !is_null($param)) {
							throw new \Exception('Parameter: '.strval($i).' must have string type');
						}
						if (!is_null($param)) {
							$result .= "'";
							$result .= $this->conn->real_escape_string($params[$i+1]);
							$result .= "'";
						} else {
							$result .= 'NULL';
						}
					break;
					case 'd' :
						$param=$params[$i+1];
						if (!is_float($param) && !is_null($param)) {
							throw new \Exception('Parameter: '.strval($i).' must have float type');
						}
						$result .= strval($params[$i+1]);

					break;
					case 'b' :
						$param=$params[$i+1];
						if (!is_string($param) && !is_null($param)) {
							throw new \Exception('Parameter: '.strval($i).' must be a string representation of the blob value');
						}

						$result .= $this->conn->real_escape_string($params[$i]);
					break;
					default :
						throw new \Exception('Parameter: '.strval($i).' has an unexpected type: '.$paramsTypes[$i]);
					break;
				}
			}
			$result .= $chunks[$paramsCount];
		}
		return $result;
	}
}
