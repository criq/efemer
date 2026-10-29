<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PostsTimeframeSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "posts_timeframe_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "posts")) {
			return;
		}

		if (!$this->hasColumn($connection, "posts", "startsAt")) {
			$connection->createQuery("
				ALTER TABLE posts
				ADD startsAt datetime NULL AFTER publishedAt,
				ADD endsAt datetime NULL AFTER startsAt,
				ADD place varchar(255) COLLATE utf8mb4_czech_ci NULL AFTER endsAt,
				ADD isInvitation tinyint(1) NOT NULL DEFAULT 0 AFTER place,
				ADD KEY posts_startsAt (startsAt),
				ADD KEY posts_isInvitation (isInvitation)
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
