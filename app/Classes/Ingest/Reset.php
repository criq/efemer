<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Post;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use App\Classes\ThirdParty\Google\Cloud;
use Katu\PDO\Connection;

class Reset
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function run(): void
	{
		$connection = Connection::getInstance("app");

		$this->log(ConsoleStyle::step("Collecting GCS URIs linked to posts / ingest / pages…"));
		$urisToDelete = $this->filterUrisToCurrentEnvironment($this->collectContentUris($connection));
		$this->log(ConsoleStyle::step(sprintf(
			"Found %s content storage URIs in %s.",
			count($urisToDelete),
			Cloud::getFilesEnvironmentShard(),
		)));

		$this->log(ConsoleStyle::step("Clearing post ↔ ingest links…"));
		if ($this->hasTable($connection, "posts") && $this->hasColumn($connection, "posts", "ingestItemId")) {
			$connection->createQuery("UPDATE posts SET ingestItemId = NULL")->getResult();
		}
		if ($this->hasTable($connection, "ingest_items")) {
			$connection->createQuery("UPDATE ingest_items SET postId = NULL, eventId = NULL, duplicateOfId = NULL")->getResult();
		}

		$this->log(ConsoleStyle::step("Deleting posts…"));
		if ($this->hasTable($connection, "posts")) {
			$count = 0;
			foreach (Post::getAll() as $post) {
				$post->delete();
				$count++;
			}
			$this->log(ConsoleStyle::ok("Deleted") . " " . ConsoleStyle::count($count) . " posts.");
		}

		$this->log(ConsoleStyle::step("Deleting ingest items…"));
		if ($this->hasTable($connection, "ingest_items")) {
			$connection->createQuery("DELETE FROM ingest_items")->getResult();
			$this->log(ConsoleStyle::ok("Cleared") . " ingest_items.");
		}

		$this->log(ConsoleStyle::step("Wiping legacy page-builder pages…"));
		$pageCount = 0;
		foreach (Page::getAll() as $page) {
			foreach ($page->getPageComponents() as $pageComponent) {
				if ($pageComponent instanceof PageComponent && $pageComponent->isRoot()) {
					$pageComponent->delete();
				}
			}
			$page->delete();
			$pageCount++;
		}
		$this->log(ConsoleStyle::ok("Deleted") . " " . ConsoleStyle::count($pageCount) . " pages.");

		$this->log(ConsoleStyle::step("Deleting orphan storage_files rows (keeps event heroes)…"));
		$deleted = $this->deleteOrphanStorageRows($connection);
		$this->log(ConsoleStyle::ok("Removed") . " " . ConsoleStyle::count($deleted) . " storage_files rows.");

		$this->log(ConsoleStyle::step("Deleting collected GCS objects…"));
		$this->deleteGcsUris($urisToDelete);

		$this->purgeUnreferencedGcs($connection);

		$this->clearLocalDirs();
		$this->log(ConsoleStyle::done("reset"));
	}

	/**
	 * @return array<string, true>
	 */
	private function collectContentUris(Connection $connection): array
	{
		$uris = [];
		$queries = [];
		if ($this->hasTable($connection, "post_files")) {
			$queries[] = "
				SELECT sf.uri FROM storage_files sf
				INNER JOIN post_files pf ON pf.storageFileId = sf.id
			";
		}
		if ($this->hasTable($connection, "ingest_item_files")) {
			$queries[] = "
				SELECT sf.uri FROM storage_files sf
				INNER JOIN ingest_item_files iif ON iif.storageFileId = sf.id
			";
		}
		if ($this->hasTable($connection, "page_components_storage_files")) {
			$queries[] = "
				SELECT sf.uri FROM storage_files sf
				INNER JOIN page_components_storage_files pcsf ON pcsf.storageFileId = sf.id
			";
		}

		foreach ($queries as $sql) {
			foreach ($connection->createQuery($sql)->getResult()->getItems() as $row) {
				if (!empty($row["uri"])) {
					$uris[(string)$row["uri"]] = true;
				}
			}
		}

		return $uris;
	}

	private function deleteOrphanStorageRows(Connection $connection): int
	{
		if (!$this->hasTable($connection, "storage_files")) {
			return 0;
		}

		$conditions = [];
		if ($this->hasTable($connection, "post_files")) {
			$conditions[] = "NOT EXISTS (SELECT 1 FROM post_files pf WHERE pf.storageFileId = sf.id)";
		}
		if ($this->hasTable($connection, "ingest_item_files")) {
			$conditions[] = "NOT EXISTS (SELECT 1 FROM ingest_item_files iif WHERE iif.storageFileId = sf.id)";
		}
		if ($this->hasTable($connection, "page_components_storage_files")) {
			$conditions[] = "NOT EXISTS (SELECT 1 FROM page_components_storage_files pcsf WHERE pcsf.storageFileId = sf.id)";
		}
		if ($this->hasTable($connection, "events")) {
			$conditions[] = "NOT EXISTS (SELECT 1 FROM events e WHERE e.heroFileId = sf.id)";
		}

		if ($conditions === []) {
			return 0;
		}

		$before = (int)($connection->createQuery("SELECT COUNT(*) AS c FROM storage_files")->getResult()->getOne()["c"] ?? 0);
		$sql = "DELETE sf FROM storage_files sf WHERE " . implode(" AND ", $conditions);
		$connection->createQuery($sql)->getResult();
		$after = (int)($connection->createQuery("SELECT COUNT(*) AS c FROM storage_files")->getResult()->getOne()["c"] ?? 0);

		return max(0, $before - $after);
	}

	/**
	 * @param array<string, true> $uris
	 */
	private function deleteGcsUris(array $uris): void
	{
		$n = 0;
		foreach (array_keys($uris) as $uri) {
			$this->deleteGcsObject($uri);
			$n++;
			if ($n % 50 === 0) {
				$this->log(ConsoleStyle::muted("…deleted {$n} GCS objects"));
			}
		}
		$this->log(ConsoleStyle::ok("Deleted") . " " . ConsoleStyle::count($n) . " collected GCS objects.");
	}

	private function purgeUnreferencedGcs(Connection $connection): void
	{
		$keep = [];
		if ($this->hasTable($connection, "storage_files")) {
			foreach ($connection->createQuery("SELECT uri FROM storage_files")->getResult()->getItems() as $row) {
				if (!empty($row["uri"])) {
					$keep[(string)$row["uri"]] = true;
				}
			}
		}

		$shardPrefix = Cloud::getFilesObjectPrefix();
		$this->log(ConsoleStyle::step("Purging unreferenced objects under gs://" . Cloud::FILES_BUCKET . "/" . $shardPrefix . "…"));

		$bucket = Cloud::getFilesBucket();
		$deleted = 0;
		$kept = 0;
		$skippedOtherEnv = 0;
		foreach ($bucket->objects(["prefix" => $shardPrefix]) as $object) {
			$uri = "gs://" . Cloud::FILES_BUCKET . "/" . $object->name();
			if (isset($keep[$uri])) {
				$kept++;
				continue;
			}

			try {
				$object->delete();
				$deleted++;
			} catch (\Throwable $exception) {
				$message = $exception->getMessage();
				if (!str_contains($message, "404") && !str_contains($message, "notFound") && !str_contains($message, "No such object")) {
					$this->log(ConsoleStyle::warn("GCS purge failed for {$uri}: " . $message));
				}
			}

			if ($deleted % 50 === 0 && $deleted > 0) {
				$this->log(ConsoleStyle::muted("…purged {$deleted} unreferenced GCS objects"));
			}
		}

		// Count other-env objects left untouched (informational).
		foreach ($bucket->objects() as $object) {
			if (!str_starts_with($object->name(), $shardPrefix)) {
				$skippedOtherEnv++;
			}
		}

		$this->log(sprintf(
			"%s GCS purge (deleted %s, kept %s, left other envs %s).",
			ConsoleStyle::ok("Done"),
			ConsoleStyle::count($deleted),
			ConsoleStyle::count($kept),
			ConsoleStyle::count($skippedOtherEnv),
		));
	}

	/**
	 * @param array<string, true> $uris
	 * @return array<string, true>
	 */
	private function filterUrisToCurrentEnvironment(array $uris): array
	{
		$filtered = [];
		foreach ($uris as $uri => $true) {
			if (Cloud::uriBelongsToCurrentEnvironment($uri)) {
				$filtered[$uri] = true;
			}
		}

		return $filtered;
	}

	private function deleteGcsObject(string $uri): void
	{
		if (!preg_match("/^gs:\\/\\/(?<bucket>.+)\\/(?<name>.+)$/U", $uri, $match)) {
			return;
		}

		try {
			$object = Cloud::getStorageClient()->bucket($match["bucket"])->object($match["name"]);
			if ($object->exists()) {
				$object->delete();
			}
		} catch (\Throwable $exception) {
			$this->log(ConsoleStyle::warn("GCS delete failed for {$uri}: " . $exception->getMessage()));
		}
	}

	private function clearLocalDirs(): void
	{
		$base = \App\App::getBaseDir();
		foreach ([
			$base . "/data/instagram",
			$base . "/tmp/instagram",
		] as $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			$this->log(ConsoleStyle::step("Clearing {$dir}…"));
			$this->removeTree($dir, true);
			if (!is_dir($dir)) {
				mkdir($dir, 0775, true);
			}
		}
	}

	private function removeTree(string $dir, bool $keepRoot = false): void
	{
		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === "." || $item === "..") {
				continue;
			}

			$path = $dir . "/" . $item;
			if (is_dir($path)) {
				$this->removeTree($path, false);
			} else {
				@unlink($path);
			}
		}

		if (!$keepRoot) {
			@rmdir($dir);
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

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
