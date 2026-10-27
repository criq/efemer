<?php

namespace App\Classes\Database\Migrations;

use App\Classes\Tools\Slugger;
use Katu\PDO\Connection;

class AddEventPath implements MigrationInterface
{
	public function getName(): string
	{
		return "add_event_path";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "events")) {
			return;
		}

		if (!$this->hasColumn($connection, "events", "path")) {
			$connection->createQuery("
				ALTER TABLE events
				ADD path varchar(300) COLLATE utf8mb4_czech_ci NULL AFTER title
			")->getResult();
		}

		$rows = $connection->createQuery("
			SELECT id, title, startsAt, path FROM events
		")->getResult()->getItems();
		$used = [];
		foreach ($rows as $row) {
			if (!empty($row["path"])) {
				$used[(string)$row["path"]] = true;
				continue;
			}

			$date = "";
			if (!empty($row["startsAt"])) {
				$date = (new \DateTime((string)$row["startsAt"]))->format("Y-m-d");
			}
			$base = Slugger::slugifyPagePath(trim($date . " " . (string)$row["title"]));
			if ($base === "" || $base === "/") {
				$base = "/udalosti/" . (int)$row["id"];
			} else {
				$base = "/udalosti/" . ltrim($base, "/");
			}

			$path = $base;
			$n = 2;
			while (isset($used[$path])) {
				$path = $base . "-" . $n;
				$n++;
			}
			$used[$path] = true;

			$connection->createQuery("
				UPDATE events SET path = :path WHERE id = :id
			", [
				"path" => $path,
				"id" => $row["id"],
			])->getResult();
		}

		if (!$this->hasIndex($connection, "events", "events_path")) {
			$connection->createQuery("
				ALTER TABLE events
				MODIFY path varchar(300) COLLATE utf8mb4_czech_ci NOT NULL,
				ADD UNIQUE KEY events_path (path)
			")->getResult();
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

	private function hasIndex(Connection $connection, string $table, string $index): bool
	{
		$rows = $connection->createQuery("SHOW INDEX FROM `{$table}`")->getResult()->getItems();
		foreach ($rows as $row) {
			if (($row["Key_name"] ?? "") === $index) {
				return true;
			}
		}

		return false;
	}
}
