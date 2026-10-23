<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class ComponentTreeParentId implements MigrationInterface
{
	public function getName(): string
	{
		return "component_tree_parent_id";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasColumn($connection, "page_components", "parentId")) {
			$connection->createQuery("
				ALTER TABLE page_components
				ADD COLUMN parentId int unsigned NULL AFTER pageId,
				ADD KEY page_components_fk_parentId (parentId),
				ADD CONSTRAINT page_components_fk_parentId
					FOREIGN KEY (parentId) REFERENCES page_components (id)
					ON DELETE CASCADE ON UPDATE CASCADE
			")->getResult();
		}

		if ($this->hasTable($connection, "page_component_gallery_items")) {
			$connection->createQuery("
				UPDATE page_components AS child
				INNER JOIN page_component_gallery_items AS item
					ON item.childPageComponentId = child.id
				INNER JOIN page_components AS parent
					ON parent.id = item.galleryPageComponentId
					AND parent.pageId = child.pageId
				SET child.parentId = item.galleryPageComponentId,
					child.position = item.position
			")->getResult();

			$connection->createQuery("DROP TABLE page_component_gallery_items")->getResult();
		}

		$connection->createQuery("
			UPDATE page_components
			SET kind = \"COLUMNS\"
			WHERE kind = \"GALLERY\"
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
		foreach ($connection->createQuery("DESCRIBE `{$table}`")->getResult()->getItems() as $row) {
			if (($row["Field"] ?? null) === $column) {
				return true;
			}
		}

		return false;
	}
}
