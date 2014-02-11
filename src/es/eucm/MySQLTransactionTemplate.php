<?php

namespace es\eucm;

/**

SELECT ... LOCK IN SHARE MODE sets a shared mode lock on any rows that are read. Other sessions can read the rows, but cannot modify them until your transaction commits. If any of these rows were changed by another transaction that has not yet committed, your query waits until that transaction ends and then uses the latest values.

For index records the search encounters, SELECT ... FOR UPDATE blocks other sessions from doing SELECT ... LOCK IN SHARE MODE or from reading in certain transaction isolation levels. Consistent reads will ignore any locks set on the records that exist in the read view. (Old versions of a record cannot be locked; they will be reconstructed by applying undo logs on an in-memory copy of the record.)

These clauses are primarily useful when dealing with tree-structured or graph-structured data, either in a single table or split across multiple tables.

Locks set by LOCK IN SHARE MODE and FOR UPDATE reads are released when the transaction is committed or rolled back.
 */
class MySQLTransactionTemplate {
	const READ_ONLY = 1;
	const READ_WRITE = 2;
	const ISOLATION_LEVEL_READ_COMMITED = 4;
	const ISOLATION_LEVEL_REPETIBLE_READ = 8;
	const ISOLATION_LEVEL_SERIALIZABLE = 16;
	const DEFAULT_TX_OPTIONS = 6; // self::READ_WRITE | self::ISOLATION_LEVEL_READ_COMMITED;

	private $conn;

	public function __construct(\mysqli $conn, $txOptions = self::DEFAULT_TX_OPTIONS) {
		$this->conn = $conn;
		$this->txOptions = $txOptions;	
	}

	private function startTransaction($txOptions) {
		// XXX There is a begin_transaction() in PHP >= 5.5
		$setTransaction = 'SET TRANSACTION ISOLATION LEVEL ' . $this->getIsolationLevel($txOptions);

		if(!$this->conn->query($setTransaction)) {
			throw new \Exception('Error configuring the transaction: '.$setTransaction.', '.$this->conn->error, $this->conn->errno);
		}
		if ($this->conn->server_version >= 50605 ) { // MySQL >= 5.6.5
			$setTransaction = 'START TRANSACTION '.$this->getTransactionMode($txOptions);
			if(!$this->conn->query($setTransaction)) {
				throw new \Exception('Error configuring the transaction: '.$setTransaction.', '.$this->conn->error, $this->conn->errno);
			}
		}

		if(!$this->conn->autocommit(FALSE)) {
			throw new \Exception('Error configuring the transaction: '.$setTransaction.', '.$this->conn->error, $this->conn->errno);
		}
	}

	private function getIsolationLevel($txOptions) {
		$isolationLevel='';
		if (($txOptions & self::ISOLATION_LEVEL_READ_COMMITED) !== 0) {
			$isolationLevel = 'READ COMMITTED';
		} else if (($txOptions & self::ISOLATION_LEVEL_REPETIBLE_READ) !== 0) {
			$isolationLevel = 'REPETIBLE READ';
		} else if (($txOptions & self::ISOLATION_LEVEL_SERIALIZABLE) !== 0) {
			$isolationLevel = 'SERIALIZABLE';
		} else if (((self::ISOLATION_LEVEL_READ_COMMITED | self::ISOLATION_LEVEL_REPETIBLE_READ | self::ISOLATION_LEVEL_SERIALIZABLE) & $txOptions)!==0) {
			throw new \Exception("Can not setup more than one isolation level");
		}
		return $isolationLevel;
	}

	private function getTransactionMode($txOptions) {
		$mode='';
		if (($txOptions & self::READ_ONLY) !== 0) {
			$mode = 'READ ONLY';
		} else if (($txOptions & self::READ_WRITE) !== 0) {
			$mode = 'READ WRITE';
		} else if (((self::READ_ONLY | self::READ_WRITE) & $txOptions)!==0) {
			throw new \Exception("Can not setup both READ_ONLY and READ_WRITE mode");
		}
		return $mode;
	}

	public function execute($transactionalOp) {
		try {
			$this->startTransaction($this->txOptions);
			$result = \call_user_func($transactionalOp, $this->conn);
			$this->conn->commit();
			$this->conn->autocommit(TRUE);
			return $result;
		} catch(\Exception $e) {
			$this->conn->rollback();
			throw $e;
		}
	}
}
