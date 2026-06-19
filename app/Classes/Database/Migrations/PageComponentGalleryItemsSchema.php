<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class PageComponentGalleryItemsSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "page_component_gallery_items_schema";
	}

	public function up(Connection $connection): void
	{
		if ($this->hasTable($connection, "page_component_gallery_items")) {
			return;
		}

		$connection->createQuery("
			CREATE TABLE page_component_gallery_items (
				id int unsigned NOT NULL AUTO_INCREMENT,
				galleryPageComponentId int unsigned NOT NULL,
				childPageComponentId int unsigned NOT NULL,
				position smallint unsigned NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY page_component_gallery_items_gallery_child (galleryPageComponentId, childPageComponentId),
				KEY page_component_gallery_items_fk_galleryPageComponentId (galleryPageComponentId),
				KEY page_component_gallery_items_fk_childPageComponentId (childPageComponentId),
				CONSTRAINT page_component_gallery_items_fk_galleryPageComponentId FOREIGN KEY (galleryPageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT page_component_gallery_items_fk_childPageComponentId FOREIGN KEY (childPageComponentId) REFERENCES page_components (id) ON DELETE CASCADE ON UPDATE CASCADE
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
