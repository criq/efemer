<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

interface MigrationInterface
{
	public function getName(): string;

	public function up(Connection $connection): void;
}
