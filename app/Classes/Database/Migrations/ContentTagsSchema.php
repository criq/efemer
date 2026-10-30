<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class ContentTagsSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "content_tags_schema";
	}

	public function up(Connection $connection): void
	{
		if ($this->hasTable($connection, "ingest_items") && !$this->hasColumn($connection, "ingest_items", "tags")) {
			$connection->createQuery("
				ALTER TABLE ingest_items
				ADD tags mediumtext COLLATE utf8mb4_czech_ci NULL AFTER isInvitation
			")->getResult();
		}

		if ($this->hasTable($connection, "posts") && !$this->hasColumn($connection, "posts", "tags")) {
			$connection->createQuery("
				ALTER TABLE posts
				ADD tags mediumtext COLLATE utf8mb4_czech_ci NULL AFTER isInvitation
			")->getResult();
		}

		if ($this->hasColumn($connection, "ingest_items", "tags")) {
			$connection->createQuery("
				UPDATE ingest_items
				SET tags = JSON_ARRAY(\"invitation\")
				WHERE isInvitation = 1
					AND (tags IS NULL OR tags = \"\" OR tags = \"null\" OR tags = \"[]\")
			")->getResult();
		}

		if ($this->hasColumn($connection, "posts", "tags")) {
			$connection->createQuery("
				UPDATE posts
				SET tags = JSON_ARRAY(\"invitation\")
				WHERE isInvitation = 1
					AND (tags IS NULL OR tags = \"\" OR tags = \"null\" OR tags = \"[]\")
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
