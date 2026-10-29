<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PostFileEnrichmentSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "post_file_enrichment_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "post_files")) {
			return;
		}

		if (!$this->hasColumn($connection, "post_files", "rating")) {
			$connection->createQuery("
				ALTER TABLE post_files
				ADD rating decimal(3,2) NULL AFTER caption,
				ADD role varchar(32) COLLATE utf8mb4_czech_ci NULL AFTER rating,
				ADD demote tinyint(1) NOT NULL DEFAULT 0 AFTER role
			")->getResult();
		}
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
