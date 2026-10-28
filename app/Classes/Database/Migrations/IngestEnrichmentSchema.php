<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class IngestEnrichmentSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "ingest_enrichment_schema";
	}

	public function up(Connection $connection): void
	{
		if ($this->hasTable($connection, "ingest_items")) {
			if (!$this->hasColumn($connection, "ingest_items", "coverFileId")) {
				$connection->createQuery("
					ALTER TABLE ingest_items
					ADD coverFileId int unsigned NULL AFTER eventId,
					ADD coverRationale text COLLATE utf8mb4_czech_ci NULL AFTER coverFileId,
					ADD coverLocked tinyint(1) NOT NULL DEFAULT 0 AFTER coverRationale,
					ADD seriesKey varchar(64) COLLATE utf8mb4_czech_ci NULL AFTER coverLocked,
					ADD palette mediumtext COLLATE utf8mb4_czech_ci NULL AFTER seriesKey,
					ADD layoutHints mediumtext COLLATE utf8mb4_czech_ci NULL AFTER palette,
					ADD enrichmentVersion varchar(32) COLLATE utf8mb4_czech_ci NULL AFTER layoutHints,
					ADD enrichedAt datetime NULL AFTER enrichmentVersion,
					ADD KEY ingest_items_coverFileId (coverFileId),
					ADD KEY ingest_items_enrichmentVersion (enrichmentVersion)
				")->getResult();
			}
		}

		if ($this->hasTable($connection, "ingest_item_files")) {
			if (!$this->hasColumn($connection, "ingest_item_files", "rating")) {
				$connection->createQuery("
					ALTER TABLE ingest_item_files
					ADD rating decimal(3,2) NULL AFTER galleryIndex,
					ADD isCover tinyint(1) NOT NULL DEFAULT 0 AFTER rating,
					ADD role varchar(32) COLLATE utf8mb4_czech_ci NULL AFTER isCover,
					ADD roleRationale text COLLATE utf8mb4_czech_ci NULL AFTER role,
					ADD roleLocked tinyint(1) NOT NULL DEFAULT 0 AFTER roleRationale,
					ADD altText text COLLATE utf8mb4_czech_ci NULL AFTER roleLocked,
					ADD orientation varchar(16) COLLATE utf8mb4_czech_ci NULL AFTER altText,
					ADD focalCrop mediumtext COLLATE utf8mb4_czech_ci NULL AFTER orientation,
					ADD demote tinyint(1) NOT NULL DEFAULT 0 AFTER focalCrop,
					ADD KEY ingest_item_files_role (role),
					ADD KEY ingest_item_files_isCover (isCover)
				")->getResult();
			}
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
