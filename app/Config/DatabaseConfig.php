<?php

namespace App\Config;

use Katu\Config\DatabaseConnectionConfigCollection;
use Katu\Config\EnvConfig;
use Katu\Config\MySQLDatabaseConnectionConfig;

class DatabaseConfig extends \Katu\Config\DatabaseConfig
{
	public function getDatabaseConnectionConfigs(): DatabaseConnectionConfigCollection
	{
		return new DatabaseConnectionConfigCollection([
			(new MySQLDatabaseConnectionConfig("app", (new EnvConfig)->getVariable("MYSQL_HOST") ?: "mysql", (new EnvConfig)->getVariable("MYSQL_USER"), (new EnvConfig)->getVariable("MYSQL_PASSWORD"), (new EnvConfig)->getVariable("MYSQL_DATABASE")))
				->setCharset("UTF8MB4"),
		]);
	}
}
