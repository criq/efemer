<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class AddPagePath implements MigrationInterface
{
	public function getName(): string
	{
		return "add_page_path";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "pages")) {
			return;
		}

		if ($this->hasColumn($connection, "pages", "path")) {
			return;
		}

		$connection->createQuery("
			ALTER TABLE pages
			ADD COLUMN path varchar(200) COLLATE utf8mb4_czech_ci DEFAULT NULL AFTER title,
			ADD UNIQUE KEY pages_path (path)
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
