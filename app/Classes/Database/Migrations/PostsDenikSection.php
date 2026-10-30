<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

/**
 * Collapse himmelhaus / workshopy / umeni into one dated journal section (denik).
 */
class PostsDenikSection implements MigrationInterface
{
	public function getName(): string
	{
		return "posts_denik_section";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "posts") || !$this->hasColumn($connection, "posts", "section")) {
			return;
		}

		foreach (["himmelhaus", "workshopy", "umeni"] as $legacy) {
			$prefix = "/" . $legacy . "/";
			$connection->createQuery("
				UPDATE posts
				SET
					path = CONCAT(\"/denik/\", SUBSTRING(path, :cut)),
					section = \"denik\"
				WHERE section = :legacy
					AND path LIKE :likePrefix
			")->setParams([
				"cut" => strlen($prefix) + 1,
				"legacy" => $legacy,
				"likePrefix" => $prefix . "%",
			])->getResult();

			$connection->createQuery("
				UPDATE posts
				SET section = \"denik\", path = CONCAT(\"/denik/\", id)
				WHERE section = :legacy
			")->setParam("legacy", $legacy)->getResult();
		}

		if ($this->hasTable($connection, "events") && $this->hasColumn($connection, "events", "section")) {
			$connection->createQuery("
				UPDATE events
				SET section = \"denik\"
				WHERE section IN (\"himmelhaus\", \"workshopy\", \"umeni\")
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
