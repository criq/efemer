<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class IngestSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "ingest_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "ingest_items")) {
			$connection->createQuery("
				CREATE TABLE ingest_items (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					timeUpdated datetime DEFAULT NULL,
					source varchar(32) COLLATE utf8mb4_czech_ci NOT NULL,
					sourceKey varchar(500) COLLATE utf8mb4_czech_ci NOT NULL,
					sourceUrl varchar(1000) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					instagramAccount varchar(64) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					instagramShortcode varchar(32) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					title varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					postedAt datetime DEFAULT NULL,
					bodyHtml mediumtext COLLATE utf8mb4_czech_ci,
					contentHash char(64) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					status varchar(32) COLLATE utf8mb4_czech_ci NOT NULL DEFAULT \"loaded\",
					kind varchar(32) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					keywords mediumtext COLLATE utf8mb4_czech_ci,
					themes mediumtext COLLATE utf8mb4_czech_ci,
					entities mediumtext COLLATE utf8mb4_czech_ci,
					qualityFlags mediumtext COLLATE utf8mb4_czech_ci,
					categories mediumtext COLLATE utf8mb4_czech_ci,
					classificationRaw mediumtext COLLATE utf8mb4_czech_ci,
					embedding mediumblob,
					duplicateOfId int unsigned DEFAULT NULL,
					postId int unsigned DEFAULT NULL,
					eventId int unsigned DEFAULT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY ingest_items_source_key (source, sourceKey),
					KEY ingest_items_status (status),
					KEY ingest_items_postedAt (postedAt),
					KEY ingest_items_instagram (instagramAccount, instagramShortcode),
					KEY ingest_items_duplicateOfId (duplicateOfId),
					KEY ingest_items_postId (postId),
					KEY ingest_items_eventId (eventId)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
			")->getResult();
		}

		if (!$this->hasTable($connection, "ingest_item_files")) {
			$connection->createQuery("
				CREATE TABLE ingest_item_files (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					ingestItemId int unsigned NOT NULL,
					storageFileId int unsigned NOT NULL,
					position smallint unsigned NOT NULL,
					caption varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					keywords mediumtext COLLATE utf8mb4_czech_ci,
					contentHash char(64) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					sourceSlot varchar(32) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					galleryIndex smallint unsigned DEFAULT NULL,
					PRIMARY KEY (id),
					KEY ingest_item_files_item (ingestItemId),
					KEY ingest_item_files_storage (storageFileId),
					KEY ingest_item_files_sourceSlot (sourceSlot),
					CONSTRAINT ingest_item_files_fk_item FOREIGN KEY (ingestItemId) REFERENCES ingest_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
					CONSTRAINT ingest_item_files_fk_storage FOREIGN KEY (storageFileId) REFERENCES storage_files (id) ON DELETE CASCADE ON UPDATE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
			")->getResult();
		}

		if ($this->hasTable($connection, "storage_files") && !$this->hasColumn($connection, "storage_files", "contentHash")) {
			$connection->createQuery("
				ALTER TABLE storage_files
				ADD contentHash char(64) COLLATE utf8mb4_czech_ci NULL AFTER uri,
				ADD KEY storage_files_contentHash (contentHash)
			")->getResult();
		}

		if ($this->hasTable($connection, "posts")) {
			if (!$this->hasColumn($connection, "posts", "ingestItemId")) {
				$connection->createQuery("
					ALTER TABLE posts
					ADD ingestItemId int unsigned NULL AFTER classificationConfidence,
					ADD KEY posts_ingestItemId (ingestItemId)
				")->getResult();
			}
			if (!$this->hasColumn($connection, "posts", "layoutJson")) {
				$connection->createQuery("
					ALTER TABLE posts
					ADD layoutJson mediumtext COLLATE utf8mb4_czech_ci NULL AFTER ingestItemId
				")->getResult();
			}
		}

		if ($this->hasTable($connection, "post_files") && !$this->hasColumn($connection, "post_files", "keywords")) {
			$connection->createQuery("
				ALTER TABLE post_files
				ADD keywords mediumtext COLLATE utf8mb4_czech_ci NULL AFTER caption
			")->getResult();
		}

		$this->addForeignKeys($connection);
	}

	private function addForeignKeys(Connection $connection): void
	{
		if ($this->hasTable($connection, "ingest_items") && $this->hasTable($connection, "posts")) {
			if (!$this->hasConstraint($connection, "ingest_items", "ingest_items_fk_postId")) {
				$connection->createQuery("
					ALTER TABLE ingest_items
					ADD CONSTRAINT ingest_items_fk_postId FOREIGN KEY (postId) REFERENCES posts (id) ON DELETE SET NULL ON UPDATE CASCADE
				")->getResult();
			}
			if (!$this->hasConstraint($connection, "posts", "posts_fk_ingestItemId")) {
				$connection->createQuery("
					ALTER TABLE posts
					ADD CONSTRAINT posts_fk_ingestItemId FOREIGN KEY (ingestItemId) REFERENCES ingest_items (id) ON DELETE SET NULL ON UPDATE CASCADE
				")->getResult();
			}
		}

		if ($this->hasTable($connection, "ingest_items") && $this->hasTable($connection, "events")) {
			if (!$this->hasConstraint($connection, "ingest_items", "ingest_items_fk_eventId")) {
				$connection->createQuery("
					ALTER TABLE ingest_items
					ADD CONSTRAINT ingest_items_fk_eventId FOREIGN KEY (eventId) REFERENCES events (id) ON DELETE SET NULL ON UPDATE CASCADE
				")->getResult();
			}
		}

		if ($this->hasTable($connection, "ingest_items") && !$this->hasConstraint($connection, "ingest_items", "ingest_items_fk_duplicateOfId")) {
			$connection->createQuery("
				ALTER TABLE ingest_items
				ADD CONSTRAINT ingest_items_fk_duplicateOfId FOREIGN KEY (duplicateOfId) REFERENCES ingest_items (id) ON DELETE SET NULL ON UPDATE CASCADE
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

	private function hasConstraint(Connection $connection, string $table, string $name): bool
	{
		$rows = $connection->createQuery("
			SELECT CONSTRAINT_NAME
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :table
				AND CONSTRAINT_NAME = :name
		", [
			"table" => $table,
			"name" => $name,
		])->getResult()->getItems();

		return count($rows) > 0;
	}
}
