<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class StorageFilesSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "storage_files_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "storage_files")) {
			$this->createTable($connection);
		}

		if ($this->hasTable($connection, "page_component_files")) {
			$this->migrateFromPageComponentFiles($connection);
			$this->dropPageComponentFiles($connection);
		}

		if ($this->hasTable($connection, "page_files")) {
			$this->dropPageFiles($connection);
		}
	}

	private function createTable(Connection $connection): void
	{
		$connection->createQuery("
			CREATE TABLE storage_files (
				id int unsigned NOT NULL AUTO_INCREMENT,
				timeCreated datetime NOT NULL,
				pageComponentId int unsigned NOT NULL,
				uri varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
				position smallint unsigned DEFAULT NULL,
				PRIMARY KEY (id),
				KEY storage_files_fk_pageComponentId (pageComponentId),
				CONSTRAINT storage_files_fk_pageComponentId FOREIGN KEY (pageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
		")->getResult();
	}

	private function migrateFromPageComponentFiles(Connection $connection): void
	{
		$connection->createQuery("
			INSERT INTO storage_files (id, timeCreated, pageComponentId, uri, position)
			SELECT id, timeCreated, pageComponentId, uri, position
			FROM page_component_files
		")->getResult();
	}

	private function dropPageComponentFiles(Connection $connection): void
	{
		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 0")->getResult();
		$this->dropForeignKeyIfExists($connection, "page_component_files", "page_component_files_fk_pageComponentId");
		$connection->createQuery("DROP TABLE page_component_files")->getResult();
		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 1")->getResult();
	}

	private function dropPageFiles(Connection $connection): void
	{
		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 0")->getResult();
		$this->dropForeignKeyIfExists($connection, "page_files", "page_files_fk_pageId");
		$connection->createQuery("DROP TABLE page_files")->getResult();
		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 1")->getResult();
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
