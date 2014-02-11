<?php

namespace es\eucm;

class MySQLConnectionProvider {
	public static function createConnection($server, $user, $password, $database) {
		$mysqli = new \mysqli($server, $user, $password, $database);
		if ($mysqli->connect_errno) {
		    throw new \Exception('Failed to connect to MySQL: '.$mysqli->connect_error, $mysqli->connect_errno);
		}
		if (!$mysqli->set_charset('utf8')) {
		    throw new \Exception('Failed set charset to utf8: '.$mysqli->error, $mysqli->errno);
		}
		return $mysqli;
	}
}