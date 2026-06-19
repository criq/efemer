<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PageComponentStorageFilesSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "page_component_storage_files_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "storage_files")) {
			return;
		}

		if (!$this->hasTable($connection, "page_components_storage_files")) {
			$this->createJunctionTable($connection);
		}

		if ($this->hasColumn($connection, "storage_files", "pageComponentId")) {
			$this->migrateFromStorageFiles($connection);
			$this->stripComponentMetadataFromStorageFiles($connection);
		}
	}

	private function createJunctionTable(Connection $connection): void
	{
		$connection->createQuery("
			CREATE TABLE page_components_storage_files (
				id int unsigned NOT NULL AUTO_INCREMENT,
				pageComponentId int unsigned NOT NULL,
				storageFileId int unsigned NOT NULL,
				position smallint unsigned DEFAULT NULL,
				caption varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY page_components_storage_files_component_file (pageComponentId, storageFileId),
				KEY page_components_storage_files_fk_pageComponentId (pageComponentId),
				KEY page_components_storage_files_fk_storageFileId (storageFileId),
				CONSTRAINT page_components_storage_files_fk_pageComponentId FOREIGN KEY (pageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT page_components_storage_files_fk_storageFileId FOREIGN KEY (storageFileId) REFERENCES storage_files (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
		")->getResult();
	}

	private function migrateFromStorageFiles(Connection $connection): void
	{
		$captionSelect = $this->hasColumn($connection, "storage_files", "caption")
			? "caption"
			: "NULL";

		$connection->createQuery("
			INSERT INTO page_components_storage_files (pageComponentId, storageFileId, position, caption)
			SELECT pageComponentId, id, position, {$captionSelect}
			FROM storage_files
		")->getResult();
	}

	private function stripComponentMetadataFromStorageFiles(Connection $connection): void
	{
		$this->dropForeignKeyIfExists($connection, "storage_files", "storage_files_fk_pageComponentId");

		if ($this->hasColumn($connection, "storage_files", "caption")) {
			$connection->createQuery("ALTER TABLE storage_files DROP COLUMN caption")->getResult();
		}

		if ($this->hasColumn($connection, "storage_files", "position")) {
			$connection->createQuery("ALTER TABLE storage_files DROP COLUMN position")->getResult();
		}

		if ($this->hasColumn($connection, "storage_files", "pageComponentId")) {
			$connection->createQuery("ALTER TABLE storage_files DROP COLUMN pageComponentId")->getResult();
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

	private function hasForeignKey(Connection $connection, string $table, string $constraintName): bool
	{
		$rows = $connection->createQuery("
			SELECT CONSTRAINT_NAME
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = :schema
				AND TABLE_NAME = :table
				AND CONSTRAINT_NAME = :constraintName
				AND CONSTRAINT_TYPE = 'FOREIGN KEY'
		", [
			"schema" => $connection->getConfig()->getDatabase(),
			"table" => $table,
			"constraintName" => $constraintName,
		])->getResult()->getItems();

		return count($rows) > 0;
	}

	private function dropForeignKeyIfExists(Connection $connection, string $table, string $constraintName): void
	{
		if (!$this->hasForeignKey($connection, $table, $constraintName)) {
			return;
		}

		$connection->createQuery("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`")->getResult();
	}
}
