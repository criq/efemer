<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Pages\Components\Templates\ImageGallery\NaturalTemplate;
use Katu\PDO\Connection;

class AddPageComponentTemplate implements MigrationInterface
{
	public function getName(): string
	{
		return "add_page_component_template";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "page_components")) {
			return;
		}

		if (!$this->hasColumn($connection, "page_components", "template")) {
			$connection->createQuery("
				ALTER TABLE page_components
				ADD template varchar(40) COLLATE utf8mb4_czech_ci DEFAULT NULL AFTER kind
			")->getResult();
		}

		$this->migrateImageGalleryDisplay($connection);
	}

	private function migrateImageGalleryDisplay(Connection $connection): void
	{
		$rows = $connection->createQuery("
			SELECT id, value, template
			FROM page_components
			WHERE kind = 'IMAGE_GALLERY'
		")->getResult()->getItems();

		$defaultTemplate = NaturalTemplate::getCode()->getConstantFormat();

		foreach ($rows as $row) {
			$template = $row["template"] ?? null;
			$value = $row["value"] ?? null;
			$data = [];

			if ($value !== null && $value !== "") {
				$decoded = json_decode($value, true);
				if (is_array($decoded)) {
					$data = $decoded;
				}
			}

			if (!$template && !empty($data["display"])) {
				$template = (string)$data["display"];
			}

			if (!$template) {
				$template = $defaultTemplate;
			}

			unset($data["display"]);
			$newValue = count($data) > 0 ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

			$connection->createQuery("
				UPDATE page_components
				SET template = :template, value = :value
				WHERE id = :id
			", [
				"template" => $template,
				"value" => $newValue,
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
