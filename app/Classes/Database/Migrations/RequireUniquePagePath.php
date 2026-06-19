<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Tools\Slugger;
use Katu\PDO\Connection;

class RequireUniquePagePath implements MigrationInterface
{
	public function getName(): string
	{
		return "require_unique_page_path";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "pages") || !$this->hasColumn($connection, "pages", "path")) {
			return;
		}

		$unsetPages = $connection->createQuery("
			SELECT id
			FROM pages
			WHERE path IS NULL OR path = ''
		")->getResult()->getItems();

		$hasHomepage = $connection->createQuery("
			SELECT id
			FROM pages
			WHERE path = :path
			LIMIT 1
		", [
			"path" => Slugger::HOMEPAGE_PATH,
		])->getResult()->getOne() !== null;

		if (count($unsetPages) === 1 && !$hasHomepage) {
			$connection->createQuery("
				UPDATE pages
				SET path = :path
				WHERE id = :id
			", [
				"path" => Slugger::HOMEPAGE_PATH,
				"id" => $unsetPages[0]["id"],
			])->getResult();
		} else {
			foreach ($unsetPages as $row) {
				$connection->createQuery("
					UPDATE pages
					SET path = :path
					WHERE id = :id
				", [
					"path" => "/stranka-" . $row["id"],
					"id" => $row["id"],
				])->getResult();
			}
		}

		if (!$this->hasIndex($connection, "pages", "pages_path")) {
			$connection->createQuery("
				ALTER TABLE pages
				ADD UNIQUE KEY pages_path (path)
			")->getResult();
		}

		$connection->createQuery("
			ALTER TABLE pages
			MODIFY path varchar(200) COLLATE utf8mb4_czech_ci NOT NULL
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

	private function hasIndex(Connection $connection, string $table, string $indexName): bool
	{
		$rows = $connection->createQuery("
			SHOW INDEX FROM `{$table}`
			WHERE Key_name = :indexName
		", [
			"indexName" => $indexName,
		])->getResult()->getItems();

		return count($rows) > 0;
	}
}
