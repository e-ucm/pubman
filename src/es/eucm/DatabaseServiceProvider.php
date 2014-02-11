<?php

namespace es\eucm;

use Silex\Application;
use Silex\ServiceProviderInterface;

class DatabaseServiceProvider implements ServiceProviderInterface {

	public function register(Application $app) {
		$app['conn'] = $app->share(function () use ($app) {
			$mysqli = new \mysqli($app['database.server'], $app['database.user'], $app['database.password'], $app['database.name']);
			if ($mysqli->connect_errno) {
				throw new \RuntimeException('MySQL Connection Error: '.$mysqli->connect_error, $mysqli->connect_errno);
			}
			if (!$mysqli->set_charset("utf8")) {
				throw new \RuntimeException('Error loading character set utf8: ' . $mysqli->error, $mysqli->errno);
			}
			return $mysqli;
		});
	}

	public function boot(Application $app) {
	}
}
