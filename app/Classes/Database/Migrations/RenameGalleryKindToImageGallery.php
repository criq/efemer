<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class RenameGalleryKindToImageGallery implements MigrationInterface
{
	public function getName(): string
	{
		return "rename_gallery_kind_to_image_gallery";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "page_components")) {
			return;
		}

		$connection->createQuery("
			UPDATE page_components
			SET kind = 'IMAGE_GALLERY'
			WHERE kind = 'GALLERY'
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
