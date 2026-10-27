<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class AddEventUrl implements MigrationInterface
{
	public function getName(): string
	{
		return "add_event_url";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "events")) {
			return;
		}

		if ($this->hasColumn($connection, "events", "url")) {
			return;
		}

		$connection->createQuery("
			ALTER TABLE events
			ADD url varchar(1000) COLLATE utf8mb4_czech_ci DEFAULT NULL AFTER bodyHtml
		")->getResult();
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

	private function hasColumn(Connection $connection, string $table, string $column): bool
	{
		$rows = $connection->createQuery("DESCRIBE `{$table}`")->getResult()->getItems();

		foreach ($rows as $row) {
			if ($row["Field"] === $column) {
				return true;
			}
		}

		return false;
	}
}
