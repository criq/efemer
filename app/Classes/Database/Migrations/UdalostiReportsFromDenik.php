<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

/**
 * Move lasting workshop / report posts from denik into udalosti (reports, not invitations).
 */
class UdalostiReportsFromDenik implements MigrationInterface
{
	public function getName(): string
	{
		return "udalosti_reports_from_denik";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "posts") || !$this->hasTable($connection, "ingest_items")) {
			return;
		}

		$connection->createQuery("
			UPDATE posts p
			INNER JOIN ingest_items i ON i.id = p.ingestItemId
			SET
				p.path = CONCAT(\"/udalosti/\", SUBSTRING(p.path, 8)),
				p.section = \"udalosti\"
			WHERE p.section = \"denik\"
				AND p.isInvitation = 0
				AND p.path LIKE \"/denik/%\"
				AND (
					i.kind = \"workshop\"
					OR JSON_CONTAINS(COALESCE(i.tags, \"[]\"), JSON_QUOTE(\"report\"))
					OR JSON_CONTAINS(COALESCE(p.tags, \"[]\"), JSON_QUOTE(\"report\"))
				)
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
