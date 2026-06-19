<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Pages\PageGallery\TemplateCollection;
use Katu\PDO\Connection;

class AddPageComponentPageTemplate implements MigrationInterface
{
	public function getName(): string
	{
		return "add_page_component_page_template";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "page_component_pages")) {
			return;
		}

		if ($this->hasColumn($connection, "page_component_pages", "template")) {
			return;
		}

		$default = TemplateCollection::getDefaultCode();

		$connection->createQuery("
			ALTER TABLE page_component_pages
			ADD template varchar(40) COLLATE utf8mb4_czech_ci NOT NULL DEFAULT '{$default}' AFTER position
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
