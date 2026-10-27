<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class ContentSchema implements MigrationInterface
{
	public function getName(): string
	{
		return "content_schema";
	}

	public function up(Connection $connection): void
	{
		if (!$this->hasTable($connection, "posts")) {
			$connection->createQuery("
				CREATE TABLE posts (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					publishedAt datetime DEFAULT NULL,
					title varchar(300) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					path varchar(300) COLLATE utf8mb4_czech_ci NOT NULL,
					section varchar(32) COLLATE utf8mb4_czech_ci NOT NULL,
					excerpt text COLLATE utf8mb4_czech_ci,
					bodyHtml mediumtext COLLATE utf8mb4_czech_ci,
					status varchar(20) COLLATE utf8mb4_czech_ci NOT NULL DEFAULT \"review\",
					source varchar(20) COLLATE utf8mb4_czech_ci NOT NULL DEFAULT \"manual\",
					instagramAccount varchar(64) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					instagramShortcode varchar(32) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					classificationConfidence decimal(4,3) DEFAULT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY posts_path (path),
					UNIQUE KEY posts_instagram (instagramAccount, instagramShortcode),
					KEY posts_section_status (section, status, publishedAt)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
			")->getResult();
		}

		if (!$this->hasTable($connection, "post_files")) {
			$connection->createQuery("
				CREATE TABLE post_files (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					postId int unsigned NOT NULL,
					storageFileId int unsigned NOT NULL,
					position smallint unsigned NOT NULL,
					caption varchar(500) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					PRIMARY KEY (id),
					KEY post_files_fk_postId (postId),
					KEY post_files_fk_storageFileId (storageFileId),
					CONSTRAINT post_files_fk_postId FOREIGN KEY (postId) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE,
					CONSTRAINT post_files_fk_storageFileId FOREIGN KEY (storageFileId) REFERENCES storage_files (id) ON DELETE CASCADE ON UPDATE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
			")->getResult();
		}

		if (!$this->hasTable($connection, "events")) {
			$connection->createQuery("
				CREATE TABLE events (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					title varchar(300) COLLATE utf8mb4_czech_ci NOT NULL,
					startsAt datetime NOT NULL,
					endsAt datetime DEFAULT NULL,
					place varchar(300) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					bodyHtml mediumtext COLLATE utf8mb4_czech_ci,
					heroFileId int unsigned DEFAULT NULL,
					section varchar(32) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					published tinyint unsigned NOT NULL DEFAULT 1,
					PRIMARY KEY (id),
					KEY events_startsAt (startsAt),
					KEY events_fk_heroFileId (heroFileId),
					CONSTRAINT events_fk_heroFileId FOREIGN KEY (heroFileId) REFERENCES storage_files (id) ON DELETE SET NULL ON UPDATE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
			")->getResult();
		}

		if (!$this->hasTable($connection, "appearances")) {
			$connection->createQuery("
				CREATE TABLE appearances (
					id int unsigned NOT NULL AUTO_INCREMENT,
					timeCreated datetime NOT NULL,
					title varchar(300) COLLATE utf8mb4_czech_ci NOT NULL,
					outlet varchar(200) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					url varchar(1000) COLLATE utf8mb4_czech_ci DEFAULT NULL,
					publishedAt date DEFAULT NULL,
					PRIMARY KEY (id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
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
}
