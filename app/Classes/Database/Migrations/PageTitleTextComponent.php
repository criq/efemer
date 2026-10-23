<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Tools\Slugger;
use Katu\PDO\Connection;

class PageTitleTextComponent implements MigrationInterface
{
	public function getName(): string
	{
		return "page_title_text_component";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "pages") || !$this->hasTable($connection, "page_components")) {
			return;
		}

		$pages = $connection->createQuery("
			SELECT id, title
			FROM pages
			WHERE path != :homepage
				AND title IS NOT NULL
				AND title != \"\"
			ORDER BY id
		", [
			"homepage" => Slugger::HOMEPAGE_PATH,
		])->getResult()->getItems();

		foreach ($pages as $page) {
			$pageId = (int)$page["id"];
			$title = (string)$page["title"];
			$expected = "<h1>" . htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, "UTF-8") . "</h1>";

			$first = $connection->createQuery("
				SELECT id, kind, value
				FROM page_components
				WHERE pageId = :pageId
					AND parentId IS NULL
				ORDER BY position ASC, id ASC
				LIMIT 1
			", [
				"pageId" => $pageId,
			])->getResult()->getOne();

			if ($first
				&& ($first["kind"] ?? null) === "TEXT"
				&& trim((string)($first["value"] ?? "")) === $expected
			) {
				continue;
			}

			if ($first
				&& ($first["kind"] ?? null) === "TEXT"
				&& str_starts_with(trim((string)($first["value"] ?? "")), "<h1>")
			) {
				continue;
			}

			$connection->createQuery("
				UPDATE page_components
				SET position = position + 1
				WHERE pageId = :pageId
					AND parentId IS NULL
			", [
				"pageId" => $pageId,
			])->getResult();

			$connection->createQuery("
				INSERT INTO page_components (timeCreated, pageId, parentId, kind, template, position, value)
				VALUES (NOW(), :pageId, NULL, \"TEXT\", \"DEFAULT\", 1, :value)
			", [
				"pageId" => $pageId,
				"value" => $expected,
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
}
