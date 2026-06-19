<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PagesSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "pages_schema";
	}

	public function up(Connection $connection): void
	{
		if ($this->hasTable($connection, "posts")) {
			$this->renameFromPosts($connection);

			return;
		}

		if (!$this->hasTable($connection, "pages")) {
			$this->createSchema($connection);
		}
	}

	private function renameFromPosts(Connection $connection): void
	{
		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 0")->getResult();

		if ($this->hasTable($connection, "post_block_files")) {
			$this->dropForeignKeyIfExists($connection, "post_block_files", "post_block_files_fk_postBlockId");
		}

		if ($this->hasTable($connection, "post_blocks")) {
			$this->dropForeignKeyIfExists($connection, "post_blocks", "post_blocks_fk_postId");
		}

		if ($this->hasTable($connection, "post_files")) {
			$this->dropForeignKeyIfExists($connection, "post_files", "post_files_fk_postId");
		}

		$connection->createQuery("RENAME TABLE posts TO pages")->getResult();

		if ($this->hasTable($connection, "post_blocks")) {
			$connection->createQuery("RENAME TABLE post_blocks TO page_components")->getResult();
		}

		if ($this->hasTable($connection, "post_block_files")) {
			$connection->createQuery("RENAME TABLE post_block_files TO page_component_files")->getResult();
		}

		if ($this->hasTable($connection, "post_files")) {
			$connection->createQuery("RENAME TABLE post_files TO page_files")->getResult();
		}

		if ($this->hasTable($connection, "page_components") && $this->hasColumn($connection, "page_components", "postId")) {
			$connection->createQuery("ALTER TABLE page_components CHANGE postId pageId int unsigned NOT NULL")->getResult();
		}

		if ($this->hasTable($connection, "page_component_files") && $this->hasColumn($connection, "page_component_files", "postBlockId")) {
			$connection->createQuery("ALTER TABLE page_component_files CHANGE postBlockId pageComponentId int unsigned NOT NULL")->getResult();
		}

		if ($this->hasTable($connection, "page_files") && $this->hasColumn($connection, "page_files", "postId")) {
			$connection->createQuery("ALTER TABLE page_files CHANGE postId pageId int unsigned NOT NULL")->getResult();
		}

		if ($this->hasTable($connection, "page_components") && !$this->hasForeignKey($connection, "page_components", "page_components_fk_pageId")) {
			$connection->createQuery("
				ALTER TABLE page_components
				ADD CONSTRAINT page_components_fk_pageId
				FOREIGN KEY (pageId) REFERENCES pages (id)
				ON DELETE CASCADE ON UPDATE CASCADE
			")->getResult();
		}

		if ($this->hasTable($connection, "page_component_files") && !$this->hasForeignKey($connection, "page_component_files", "page_component_files_fk_pageComponentId")) {
			$connection->createQuery("
				ALTER TABLE page_component_files
				ADD CONSTRAINT page_component_files_fk_pageComponentId
				FOREIGN KEY (pageComponentId) REFERENCES page_components (id)
				ON DELETE CASCADE ON UPDATE CASCADE
			")->getResult();
		}

		if ($this->hasTable($connection, "page_files") && !$this->hasForeignKey($connection, "page_files", "page_files_fk_pageId")) {
			$connection->createQuery("
				ALTER TABLE page_files
				ADD CONSTRAINT page_files_fk_pageId
				FOREIGN KEY (pageId) REFERENCES pages (id)
				ON DELETE CASCADE ON UPDATE CASCADE
			")->getResult();
		}

		$connection->createQuery("SET FOREIGN_KEY_CHECKS = 1")->getResult();
	}

	private function createSchema(Connection $connection): void
	{
		$connection->createQuery("
			CREATE TABLE pages (
				id int unsigned NOT NULL AUTO_INCREMENT,
				timeCreated datetime NOT NULL,
				title varchar(200) COLLATE utf8mb4_czech_ci DEFAULT NULL,
				path varchar(200) COLLATE utf8mb4_czech_ci DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY pages_path (path)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
		")->getResult();

		$connection->createQuery("
			CREATE TABLE page_components (
				id int unsigned NOT NULL AUTO_INCREMENT,
				timeCreated datetime NOT NULL,
				pageId int unsigned NOT NULL,
				kind varchar(20) COLLATE utf8mb4_czech_ci NOT NULL,
				position smallint unsigned NOT NULL,
				value text COLLATE utf8mb4_czech_ci,
				PRIMARY KEY (id),
				KEY page_components_fk_pageId (pageId),
				CONSTRAINT page_components_fk_pageId FOREIGN KEY (pageId) REFERENCES pages (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
		")->getResult();

		$connection->createQuery("
			CREATE TABLE storage_files (
				id int unsigned NOT NULL AUTO_INCREMENT,
				timeCreated datetime NOT NULL,
				pageComponentId int unsigned NOT NULL,
				uri varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL,
				position smallint unsigned DEFAULT NULL,
				caption varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL,
				PRIMARY KEY (id),
				KEY storage_files_fk_pageComponentId (pageComponentId),
				CONSTRAINT storage_files_fk_pageComponentId FOREIGN KEY (pageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
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
