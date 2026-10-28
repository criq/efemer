<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class IngestItemFileSlots implements MigrationInterface
{
	public function getName(): string
	{
		return "ingest_item_file_slots";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "ingest_item_files")) {
			return;
		}

		if (!$this->hasColumn($connection, "ingest_item_files", "sourceSlot")) {
			$connection->createQuery("
				ALTER TABLE ingest_item_files
				ADD sourceSlot varchar(32) COLLATE utf8mb4_czech_ci NULL AFTER contentHash,
				ADD galleryIndex smallint unsigned NULL AFTER sourceSlot,
				ADD KEY ingest_item_files_sourceSlot (sourceSlot)
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
