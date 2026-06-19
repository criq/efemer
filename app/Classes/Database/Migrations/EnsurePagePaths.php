<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Tools\Slugger;
use Katu\PDO\Connection;

class EnsurePagePaths implements MigrationInterface
{
	public function getName(): string
	{
		return "ensure_page_paths";
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

		if ($unsetPages === []) {
			return;
		}

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

			return;
		}

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
