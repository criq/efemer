<?php

namespace App\Classes\Instagram;

use App\Classes\Content\Post;
use App\Classes\Import\InstagramImporter;
use Katu\Files\File;

class Archiver
{
	public const ACCOUNTS = ["efemer_flowers", "himmlhaus"];
	public const DEFAULT_INTERVAL = 21600;
	public const DEFAULT_MAX_NEW = 20;
	public const INCREMENTAL_MAX_SCAN = 36;
	public const BACKFILL_MAX_SCAN = 1000;

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function loop(int $interval, bool $full = false): void
	{
		$stop = false;
		if (function_exists("pcntl_async_signals")) {
			pcntl_async_signals(true);
			$halt = static function () use (&$stop): void {
				$stop = true;
			};
			pcntl_signal(SIGTERM, $halt);
			pcntl_signal(SIGINT, $halt);
		}

		while (!$stop) {
			try {
				$this->run($full);
			} catch (SessionExpiredException $exception) {
				$this->log($exception->getMessage());
			} catch (RateLimitedException $exception) {
				$this->log($exception->getMessage());
			} catch (\Throwable $exception) {
				$this->log("Archive failed: " . $exception->getMessage());
			}

			if ($stop) {
				break;
			}

			$this->log("Sleeping {$interval}s until the next archive run.");
			$slept = 0;
			while ($slept < $interval && !$stop) {
				sleep(min(30, $interval - $slept));
				$slept += 30;
			}
		}
	}

	public function run(bool $full = false): void
	{
		$lock = $this->acquireLock();
		if ($lock === null) {
			$this->log("Another archive run is already in progress.");

			return;
		}

		try {
			$sessionid = Session::load();
			$client = new Client($sessionid);
			$importer = (new InstagramImporter)->onLog(fn (string $message) => $this->log($message));
			$state = new ArchiveState;

			foreach (self::ACCOUNTS as $account) {
				$this->archiveAccount($client, $importer, $state, $account, $full);
			}
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function archiveAccount(
		Client $client,
		InstagramImporter $importer,
		ArchiveState $state,
		string $account,
		bool $full,
	): void {
		$known = array_flip(Post::getInstagramShortcodes($account));
		$maxNew = self::DEFAULT_MAX_NEW;
		$maxScan = $full || !$state->isBackfilled($account)
			? self::BACKFILL_MAX_SCAN
			: self::INCREMENTAL_MAX_SCAN;
		$imported = 0;
		$scanned = 0;
		$reachedEnd = true;

		$this->log(sprintf(
			"Archiving @%s (%s, %d already stored).",
			$account,
			$full ? "full" : ($state->isBackfilled($account) ? "incremental" : "backfill"),
			count($known),
		));

		try {
			foreach ($client->iteratePosts($account) as $post) {
				$scanned++;
				$shortcode = (string)$post["shortcode"];
				if (isset($known[$shortcode])) {
					if ($scanned >= $maxScan) {
						$reachedEnd = false;
						break;
					}
					continue;
				}

				try {
					$dir = $this->materialize($client, $post);
					if ($importer->importOne($post, $dir)) {
						$imported++;
						$known[$shortcode] = true;
					}
				} catch (\Throwable $exception) {
					$this->log(sprintf(
						"@%s/%s failed: %s",
						$account,
						$shortcode,
						$exception->getMessage(),
					));
				}

				if ($imported >= $maxNew || $scanned >= $maxScan) {
					$reachedEnd = false;
					break;
				}

				usleep(1_200_000);
			}
		} catch (RateLimitedException $exception) {
			$reachedEnd = false;
			$this->log("@{$account}: " . $exception->getMessage());
		}

		if ($reachedEnd && $scanned > self::INCREMENTAL_MAX_SCAN) {
			$state->markBackfilled($account);
			$this->log("@{$account}: reached the end of the profile.");
		}

		$this->log(sprintf("@%s: scanned %d, imported %d.", $account, $scanned, $imported));
	}

	/**
	 * @param array<string, mixed> $post
	 */
	private function materialize(Client $client, array &$post): string
	{
		$account = (string)$post["account"];
		$shortcode = (string)$post["shortcode"];
		$dir = (string)(new File(\App\App::getBaseDir(), "tmp", "instagram", $account, $shortcode));
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException("Cannot create {$dir}");
		}

		$local = [];
		foreach ($post["media"] ?? [] as $index => $item) {
			$url = is_array($item) ? ($item["url"] ?? null) : null;
			if (!is_string($url) || $url === "") {
				continue;
			}

			$isVideo = !empty($item["is_video"]);
			$name = ($index + 1) . ($isVideo ? ".mp4" : ".jpg");
			$path = $dir . "/" . $name;
			try {
				$client->download($url, $path);
			} catch (\Throwable $exception) {
				$this->log("Media skip {$shortcode}/{$name}: " . $exception->getMessage());
				continue;
			}

			$local[] = $isVideo
				? ["local_video" => $name]
				: ["local_image" => $name];
		}

		$post["media"] = $local;
		file_put_contents(
			$dir . "/post.json",
			json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
		);

		return $dir;
	}

	/**
	 * @return resource|null
	 */
	private function acquireLock()
	{
		$dir = new File(\App\App::getBaseDir(), "tmp", "instagram");
		$dir->makeDir();
		$path = (string)(new File(\App\App::getBaseDir(), "tmp", "instagram", "archive.lock"));
		$handle = fopen($path, "c");
		if ($handle === false) {
			throw new \RuntimeException("Cannot open archive lock file.");
		}
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);

			return null;
		}

		return $handle;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
