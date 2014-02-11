<?php

namespace es\eucm;

use Silex\Application;
use Silex\ServiceProviderInterface;

class SQLTemplateProvider implements ServiceProviderInterface {

	public function register(Application $app) {
		$app['sqltemplate'] = function () use ($app) {
			return new MySQLStatementTemplate($app['sqltemplate.connection']);
		};
		$app['sqltransaction'] = function () use ($app) {
			return new MySQLTransactionTemplate($app['sqltemplate.connection']);
		};
	}

	public function boot(Application $app) {
	}
}
