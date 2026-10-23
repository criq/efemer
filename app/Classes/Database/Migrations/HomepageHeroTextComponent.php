<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Tools\Slugger;
use Katu\PDO\Connection;

class HomepageHeroTextComponent implements MigrationInterface
{
	private const HERO_HTML = "<p class=\"text-eyebrow\">ekologické květinářství</p>\n"
		. "<h1>Efemér</h1>\n"
		. "<p class=\"text-lead\">Lokální, přirozený a ohleduplný přístup ke kytici — podle místa, člověka a ročního období.</p>";

	private const HERO_MARKER = "Lokální, přirozený a ohleduplný přístup ke kytici";

	public function getName(): string
	{
		return "homepage_hero_text_component";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "pages") || !$this->hasTable($connection, "page_components")) {
			return;
		}

		$page = $connection->createQuery("
			SELECT id
			FROM pages
			WHERE path = :path
			LIMIT 1
		", [
			"path" => Slugger::HOMEPAGE_PATH,
		])->getResult()->getOne();

		if (!$page) {
			return;
		}

		$pageId = (int)$page["id"];

		$existing = $connection->createQuery("
			SELECT id
			FROM page_components
			WHERE pageId = :pageId
				AND parentId IS NULL
				AND kind = \"TEXT\"
				AND value LIKE :marker
			LIMIT 1
		", [
			"pageId" => $pageId,
			"marker" => "%" . self::HERO_MARKER . "%",
		])->getResult()->getOne();

		if ($existing) {
			return;
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
			"value" => self::HERO_HTML,
		])->getResult();
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
