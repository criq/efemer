<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class NormalizePagePaths implements MigrationInterface
{
	public function getName(): string
	{
		return "normalize_page_paths";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "pages") || !$this->hasColumn($connection, "pages", "path")) {
			return;
		}

		$connection->createQuery("
			UPDATE pages
			SET path = CONCAT('/', path)
			WHERE path IS NOT NULL
				AND path != ''
				AND path NOT LIKE '/%'
		")->getResult();

		$connection->createQuery("
			UPDATE pages
			SET path = '/'
			WHERE path IS NULL OR path = ''
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
}
