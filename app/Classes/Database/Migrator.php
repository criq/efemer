<?php

namespace App\Classes\Database;

use App\Classes\Database\Migrations\MigrationInterface;
use App\Classes\Database\Migrations\PagesSchema;
use Katu\PDO\Connection;
use Katu\Tools\Calendar\Time;

class Migrator
{
	public function run(): void
	{
		$connection = Connection::getInstance("app");

		$this->ensureMigrationsTable($connection);

		foreach ($this->getMigrations() as $migration) {
			if ($this->isApplied($connection, $migration->getName())) {
				continue;
			}

			$migration->up($connection);
			$this->markApplied($connection, $migration->getName());
		}
	}

	private function getMigrations(): array
	{
		return [
			new PagesSchema,
		];
	}

	private function ensureMigrationsTable(Connection $connection): void
	{
		if ($this->hasTable($connection, "migrations")) {
			return;
		}

		$connection->createQuery("
			CREATE TABLE migrations (
				id int unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
				timeApplied datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY migrations_name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
		")->getResult();
	}

	private function isApplied(Connection $connection, string $name): bool
	{
		$row = $connection->createQuery("
			SELECT id
			FROM migrations
			WHERE name = :name
		", [
			"name" => $name,
		])->getResult()->getOne();

		return $row !== null;
	}

	private function markApplied(Connection $connection, string $name): void
	{
		$connection->createQuery("
			INSERT INTO migrations (name, timeApplied)
			VALUES (:name, :timeApplied)
		", [
			"name" => $name,
			"timeApplied" => (new Time)->format("Y-m-d H:i:s"),
		])->getResult();
	}

	private function hasTable(Connection $connection, string $name): bool
	{
		foreach ($connection->getTableNames() as $tableName) {
			if ($tableName->getPlain() === $name) {
				return true;
			}
		}

		return false;
	}
}
