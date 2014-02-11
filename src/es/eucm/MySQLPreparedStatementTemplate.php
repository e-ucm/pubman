<?php

namespace es\eucm;

class MySQLPreparedStatementTemplate {

	private $conn;

	public function __construct(\mysqli $conn) {
		$this->conn = $conn;
	}

	public function query($query, $processRow, $params) {
		$stmt = $this->execute($query, $params);
		$fields = $this->fetchFields($selectStmt);
       
		foreach ($fields as $field) {
			$resultParams[] = &${$field};
		}

		$this->bindResult($stmt, $resultParams);

		$result = array();
		while ($stmt->fetch()) {
			$row = array();
			foreach ($fields as $field) {
				$row[$field] = $$field;
			}
			$result[] = \call_user_func($processRow, $row);
		}
		$stmt->close();
		return $result;
	}

	public function queryUnique($query, $processRow, $params) {
		$stmt = $this->execute($query, $params);
		$fields = $this->fetchFields($selectStmt);
       
		foreach ($fields as $field) {
			$resultParams[] = &${$field};
		}

		$this->bindResult($stmt, $resultParams);

		$result = FALSE;
		if($stmt->fetch()) {
			$row = array();
			foreach ($fields as $field) {
				$row[$field] = $$field;
			}
			$result = \call_user_func($processRow, $row);
		}
		$stmt->close();
		return $result;
	}

	public function insert($insert, $params) {
		$stmt = $this->execute($update, $params);
		$result = $stmt->affected_rows;
		$stmt->close();
		return $result;
	}

	public function insertReturnLastId($insert, $params) {
		$stmt = $this->execute($update, $params);
		$result = $stmt->insert_id;
		$stmt->close();
		return $result;
	}

	public function update($update, $params) {
		$stmt = $this->execute($update, $params);
		$result = $stmt->affected_rows;
		$stmt->close();
		return $result;
	}

	public function delete($delete, $params) {
		$stmt = $this->execute($delete, $params);
		$result = $stmt->affected_rows;
		$stmt->close();
		return $result;
	}

	/**
	 * To run a select statement with bound parameters and bound results.
	 * Returns an associative array two dimensional array which u can easily
	 * manipulate with array functions.
 	 */
	public function queryAllRows($query, $params) {
		$stmt = $this->execute($query, $params);
		$fields = $this->fetchFields($selectStmt);
       
		foreach ($fields as $field) {
			$resultParams[] = &${$field};
		}

		$this->bindResult($stmt, $resultParams);

		$result = array();
		$i = 0;
		while ($stmt->fetch()) {
			foreach ($fields as $field) {
				$result[$i][$field] = $$field;
			}
			$i++;
		}
		$stmt->close();
		return $result;
	}

	public function execute($query, $params) {
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->notifyError($query);
		}

		$this->bindParameters($stmt, $params);

		if (!$stmt->execute()) {
			$this->notifyError($query);
		}
		return $stmt;
	}

	private function notifyError($query) {
		throw new \Exception('Error executing statement: '.$query.' error: '.$this->conn->error, $this->conn->errno);
	}

	private function bindParameters(&$stmt, &$params) {
		call_user_func_array(array($stmt, "bind_param"), $params);
	}

	private function bindResult(&$stmt, &$resultParams){
		call_user_func_array(array($stmt, "bind_result"), $resultParams);
	}
   
	private function fetchFields($selectStmt){
		$metadata = $selectStmt->result_metadata();
		$fields = array();
		while ($field = $metadata->fetch_field()) {
			$fields_r[] = $field->name;
		}
		return $fields;
	}
}
