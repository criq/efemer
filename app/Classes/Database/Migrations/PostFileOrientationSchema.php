<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PostFileOrientationSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "post_file_orientation_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "post_files")) {
			return;
		}

		if (!$this->hasColumn($connection, "post_files", "orientation")) {
			$connection->createQuery("
				ALTER TABLE post_files
				ADD orientation varchar(16) COLLATE utf8mb4_czech_ci NULL AFTER demote
			")->getResult();
		}

		// Copy LLM orientation from ingest enrichment (one row per storage file).
		$connection->createQuery("
			UPDATE post_files pf
			INNER JOIN ingest_item_files iif ON iif.storageFileId = pf.storageFileId
			SET pf.orientation = iif.orientation
			WHERE iif.orientation IS NOT NULL
				AND iif.orientation != \"\"
				AND (pf.orientation IS NULL OR pf.orientation = \"\")
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
