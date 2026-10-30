<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

/**
 * Reports/workshops back to denik; installation/portrait → umeni; invitations stay on udalosti.
 */
class ReportsToDenikArtToUmeni implements MigrationInterface
{
	public function getName(): string
	{
		return "reports_to_denik_art_to_umeni";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "posts")) {
			return;
		}

		// Lasting reports that were parked under udalosti → denik.
		$connection->createQuery("
			UPDATE posts
			SET
				path = CONCAT(\"/denik/\", SUBSTRING(path, 11)),
				section = \"denik\"
			WHERE section = \"udalosti\"
				AND isInvitation = 0
				AND path LIKE \"/udalosti/%\"
		")->getResult();

		if (!$this->hasTable($connection, "ingest_items")) {
			return;
		}

		// Art kinds → umeni (from denik or leftover paths).
		$connection->createQuery("
			UPDATE posts p
			INNER JOIN ingest_items i ON i.id = p.ingestItemId
			SET
				p.path = CONCAT(
					\"/umeni/\",
					CASE
						WHEN p.path LIKE \"/denik/%\" THEN SUBSTRING(p.path, 8)
						WHEN p.path LIKE \"/udalosti/%\" THEN SUBSTRING(p.path, 11)
						WHEN p.path LIKE \"/umeni/%\" THEN SUBSTRING(p.path, 8)
						ELSE p.id
					END
				),
				p.section = \"umeni\"
			WHERE p.isInvitation = 0
				AND i.kind IN (\"installation\", \"portrait\")
				AND p.section IN (\"denik\", \"udalosti\", \"umeni\", \"himmelhaus\", \"workshopy\")
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
