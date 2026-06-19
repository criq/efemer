<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PageComponentPagesSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "page_component_pages_schema";
	}

	public function up(Connection $connection): void
	{
		if ($this->hasTable($connection, "page_component_pages")) {
			return;
		}

		$connection->createQuery("
			CREATE TABLE page_component_pages (
				id int unsigned NOT NULL AUTO_INCREMENT,
				pageComponentId int unsigned NOT NULL,
				pageId int unsigned NOT NULL,
				position smallint unsigned NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY page_component_pages_component_page (pageComponentId, pageId),
				KEY page_component_pages_fk_pageComponentId (pageComponentId),
				KEY page_component_pages_fk_pageId (pageId),
				CONSTRAINT page_component_pages_fk_pageComponentId FOREIGN KEY (pageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT page_component_pages_fk_pageId FOREIGN KEY (pageId) REFERENCES pages (id) ON DELETE CASCADE ON UPDATE CASCADE
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
}
