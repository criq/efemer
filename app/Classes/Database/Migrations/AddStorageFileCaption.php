<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class AddStorageFileCaption implements MigrationInterface
{
	public function getName(): string
	{
		return "add_storage_file_caption";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "storage_files")) {
			return;
		}

		if ($this->hasColumn($connection, "storage_files", "caption")) {
			return;
		}

		$connection->createQuery("
			ALTER TABLE storage_files
			ADD caption varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL AFTER position
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
