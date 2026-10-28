<?php

namespace App\Classes\Ingest;

use App\Classes\Instagram\ArchiveState;
use App\Classes\Instagram\Client;
use App\Classes\Instagram\PostUrl;
use App\Classes\Instagram\RateLimitedException;
use App\Classes\Instagram\Session;
use App\Classes\Jobs\ConsoleStyle;
use Katu\Files\File;
use Katu\Tools\Calendar\Time;

class InstagramRefresh
{
	public const ACCOUNTS = ["efemer_flowers", "himmlhaus"];
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

	public function run(string $account, bool $full = false, ?int $maxNew = null): void
	{
		if (!in_array($account, self::ACCOUNTS, true)) {
			throw new \InvalidArgumentException("Unknown Instagram account: {$account}");
		}

		$sessionid = Session::load();
		$client = new Client($sessionid);
		$loader = (new Loader)->onLog(fn (string $message) => $this->log($message));
		$state = new ArchiveState;

		$known = array_flip(IngestItem::getInstagramShortcodes($account));
		$maxNew = $maxNew ?? ($full ? PHP_INT_MAX : self::DEFAULT_MAX_NEW);
		$maxScan = $full || !$state->isBackfilled($account)
			? self::BACKFILL_MAX_SCAN
			: self::INCREMENTAL_MAX_SCAN;
		if ($full) {
			$maxScan = PHP_INT_MAX;
		}

		$imported = 0;
		$scanned = 0;
		$reachedEnd = true;

		$mode = $full ? "full" : ($state->isBackfilled($account) ? "incremental" : "backfill");
		$this->log(ConsoleStyle::step(sprintf(
			"Refreshing %s into ingest (%s, %s already stored).",
			"@" . $account,
			$mode,
			count($known),
		)));

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
					if ($this->loadOne($loader, $post, $dir)) {
						$imported++;
						$known[$shortcode] = true;
					}
				} catch (\Throwable $exception) {
					$this->log(ConsoleStyle::warn(
						"@" . $account . "/" . $shortcode . " failed: " . $exception->getMessage(),
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
			$this->log(ConsoleStyle::error("@" . $account . ": " . $exception->getMessage()));
		}

		if ($reachedEnd && $scanned > self::INCREMENTAL_MAX_SCAN) {
			$state->markBackfilled($account);
			$this->log(ConsoleStyle::done("@" . $account . " reached the end of the profile"));
		}

		$this->log(sprintf(
			"%s %s: scanned %s, loaded %s.",
			ConsoleStyle::ok("Done"),
			ConsoleStyle::title("@" . $account),
			ConsoleStyle::count($scanned),
			ConsoleStyle::count($imported),
		));
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function importMaterialized(Loader $loader, array $data, string $postDir): bool
	{
		return $this->loadOne($loader, $data, $postDir);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function loadOne(Loader $loader, array $data, string $postDir): bool
	{
		$account = (string)$data["account"];
		$shortcode = (string)$data["shortcode"];
		$sourceKey = $account . ":" . $shortcode;
		$takenAt = !empty($data["taken_at"]) ? new Time((int)$data["taken_at"]) : new Time;
		$caption = trim((string)($data["caption"] ?? ""));
		$title = $this->titleFromCaption($caption) ?: $shortcode;
		$bodyHtml = $this->captionToHtml($caption);
		$flags = [];
		if ($caption === "") {
			$flags[] = "thin_text";
		}

		$files = [];
		$videoOnly = true;
		foreach ($data["media"] ?? [] as $media) {
			$local = $media["local_image"] ?? $media["local_video"] ?? null;
			if (!$local) {
				continue;
			}

			$path = $postDir . "/" . $local;
			if (!is_file($path)) {
				continue;
			}

			$contents = (string)file_get_contents($path);
			if ($contents === "") {
				continue;
			}

			if (!empty($media["local_image"])) {
				$videoOnly = false;
			}

			$files[] = [
				"contents" => $contents,
				"fileName" => basename($path),
			];
		}

		if ($files === []) {
			$flags[] = "fetch_failed";
		} elseif ($videoOnly) {
			$flags[] = "video_heavy";
		}

		$before = IngestItem::getBySourceKey(IngestItem::SOURCE_INSTAGRAM, $sourceKey);
		$beforeHash = $before?->getContentHash();
		$item = $loader->upsert(
			IngestItem::SOURCE_INSTAGRAM,
			$sourceKey,
			PostUrl::canonical($shortcode),
			$title,
			$takenAt,
			$bodyHtml !== "" ? $bodyHtml : null,
			$files,
			$account,
			$shortcode,
			$flags,
		);

		return $beforeHash !== $item->getContentHash();
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
				$this->log(ConsoleStyle::skip("Skip") . " media " . ConsoleStyle::path($shortcode . "/" . $name) . " " . ConsoleStyle::muted($exception->getMessage()));
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

	private function titleFromCaption(string $caption): ?string
	{
		$caption = trim($caption);
		if ($caption === "") {
			return null;
		}

		$line = preg_split("/\R/u", $caption)[0] ?? $caption;
		$line = trim($line);
		if (mb_strlen($line) > 80) {
			$line = rtrim(mb_substr($line, 0, 77)) . "…";
		}

		return $line !== "" ? $line : null;
	}

	private function captionToHtml(string $caption): string
	{
		$caption = trim($caption);
		if ($caption === "") {
			return "";
		}

		$escaped = htmlspecialchars($caption, ENT_QUOTES | ENT_HTML5, "UTF-8");
		$paragraphs = preg_split("/\n{2,}/", $escaped) ?: [$escaped];
		$html = "";
		foreach ($paragraphs as $paragraph) {
			$html .= "<p>" . nl2br(trim($paragraph), false) . "</p>\n";
		}

		return trim($html);
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
