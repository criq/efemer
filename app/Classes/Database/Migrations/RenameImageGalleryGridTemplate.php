<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class RenameImageGalleryGridTemplate implements MigrationInterface
{
	public function getName(): string
	{
		return "rename_image_gallery_grid_template";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "page_components")) {
			return;
		}

		$connection->createQuery("
			UPDATE page_components
			SET template = 'NATURAL'
			WHERE kind = 'IMAGE_GALLERY' AND template = 'GRID'
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
