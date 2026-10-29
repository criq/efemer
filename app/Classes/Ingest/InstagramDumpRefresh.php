<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;
use Katu\Files\File;

/**
 * Load offline Instagram dumps from data/instagram into ingest_items.
 *
 * Supports:
 * - Archiver layout: {account}/{shortcode}/post.json + media files
 * - Instaloader layout: {account}/{date}_UTC.json.xz + {date}_UTC_N.jpg
 */
class InstagramDumpRefresh
{
	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function run(?string $root = null): void
	{
		$root = $root ?: (string)(new File(\App\App::getBaseDir(), "data", "instagram"));
		$loader = (new Loader)->onLog(fn (string $message) => $this->log($message));
		$refresh = (new InstagramRefresh)->onLog(fn (string $message) => $this->log($message));
		$imported = 0;

		foreach (InstagramRefresh::ACCOUNTS as $account) {
			$accountDir = $root . "/" . $account;
			if (!is_dir($accountDir)) {
				$this->log(ConsoleStyle::warn("Missing dump: " . $accountDir));
				continue;
			}

			$imported += $this->importArchiverPosts($refresh, $loader, $account, $accountDir);
			$imported += $this->importInstaloaderPosts($refresh, $loader, $account, $accountDir);
		}

		$this->log(ConsoleStyle::done("Instagram dump into ingest", $imported));
	}

	private function importArchiverPosts(
		InstagramRefresh $refresh,
		Loader $loader,
		string $account,
		string $accountDir,
	): int {
		$imported = 0;
		foreach (glob($accountDir . "/*/post.json") ?: [] as $jsonPath) {
			$post = json_decode((string)file_get_contents($jsonPath), true);
			if (!is_array($post) || empty($post["shortcode"])) {
				continue;
			}
			if (empty($post["account"])) {
				$post["account"] = $account;
			}
			if ($refresh->importMaterialized($loader, $post, dirname($jsonPath))) {
				$imported++;
			}
		}

		return $imported;
	}

	private function importInstaloaderPosts(
		InstagramRefresh $refresh,
		Loader $loader,
		string $account,
		string $accountDir,
	): int {
		$imported = 0;
		$metaFiles = array_merge(
			glob($accountDir . "/*_UTC.json.xz") ?: [],
			glob($accountDir . "/*_UTC.json") ?: [],
		);
		sort($metaFiles);

		foreach ($metaFiles as $metaPath) {
			try {
				$converted = $this->convertInstaloaderPost($account, $metaPath);
				if ($converted === null) {
					continue;
				}
				[$post, $postDir] = $converted;
				if ($refresh->importMaterialized($loader, $post, $postDir)) {
					$imported++;
				}
			} catch (\Throwable $exception) {
				$this->log(
					ConsoleStyle::path(basename($metaPath))
					. " "
					. ConsoleStyle::failure($exception),
				);
			}
		}

		return $imported;
	}

	/**
	 * @return array{0: array<string, mixed>, 1: string}|null
	 */
	private function convertInstaloaderPost(string $account, string $metaPath): ?array
	{
		$raw = $this->readMetaFile($metaPath);
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			throw new \RuntimeException("Invalid Instaloader JSON.");
		}

		$node = isset($decoded["node"]) && is_array($decoded["node"]) ? $decoded["node"] : $decoded;
		$shortcode = (string)($node["shortcode"] ?? "");
		if ($shortcode === "") {
			return null;
		}

		$takenAt = isset($node["taken_at_timestamp"])
			? (int)$node["taken_at_timestamp"]
			: (isset($decoded["timestamp"]) ? (int)$decoded["timestamp"] : time());
		$caption = $this->captionFromNode($node, $decoded, $metaPath);

		$stem = preg_replace('/\.json(\.xz)?$/i', "", basename($metaPath)) ?? basename($metaPath);
		$accountDir = dirname($metaPath);
		$images = $this->collectInstaloaderImages($accountDir, $stem);
		if ($images === []) {
			throw new \RuntimeException("No image files for {$stem}.");
		}

		$postDir = (string)(new File(\App\App::getBaseDir(), "tmp", "instagram", $account, $shortcode));
		if (!is_dir($postDir) && !mkdir($postDir, 0775, true) && !is_dir($postDir)) {
			throw new \RuntimeException("Cannot create {$postDir}");
		}

		$media = [];
		foreach ($images as $index => $sourcePath) {
			$name = ($index + 1) . ".jpg";
			$dest = $postDir . "/" . $name;
			if (!copy($sourcePath, $dest)) {
				throw new \RuntimeException("Could not copy {$sourcePath}");
			}
			$media[] = ["local_image" => $name];
		}

		$post = [
			"account" => $account,
			"shortcode" => $shortcode,
			"taken_at" => $takenAt,
			"caption" => $caption,
			"media" => $media,
		];
		file_put_contents(
			$postDir . "/post.json",
			json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
		);

		return [$post, $postDir];
	}

	private function readMetaFile(string $path): string
	{
		if (str_ends_with(strtolower($path), ".xz")) {
			$cmd = "xz -dc " . escapeshellarg($path);
			$out = shell_exec($cmd);
			if (!is_string($out) || $out === "") {
				throw new \RuntimeException("xz failed for {$path}");
			}

			return $out;
		}

		$raw = file_get_contents($path);
		if ($raw === false || $raw === "") {
			throw new \RuntimeException("Empty meta file {$path}");
		}

		return $raw;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array<string, mixed> $decoded
	 */
	private function captionFromNode(array $node, array $decoded, string $metaPath): string
	{
		$edges = $node["edge_media_to_caption"]["edges"] ?? null;
		if (is_array($edges) && isset($edges[0]["node"]["text"])) {
			return trim((string)$edges[0]["node"]["text"]);
		}
		if (isset($node["caption"]) && is_string($node["caption"])) {
			return trim($node["caption"]);
		}
		if (isset($decoded["caption"]) && is_string($decoded["caption"])) {
			return trim($decoded["caption"]);
		}

		$txt = preg_replace('/\.json(\.xz)?$/i', ".txt", $metaPath) ?? "";
		if ($txt !== "" && is_file($txt)) {
			return trim((string)file_get_contents($txt));
		}

		return "";
	}

	/**
	 * @return list<string>
	 */
	private function collectInstaloaderImages(string $accountDir, string $stem): array
	{
		$paths = [];
		$single = $accountDir . "/" . $stem . ".jpg";
		if (is_file($single)) {
			$paths[] = $single;
		}

		$sidecar = glob($accountDir . "/" . $stem . "_[0-9]*.jpg") ?: [];
		usort($sidecar, function (string $a, string $b) use ($stem) {
			$na = (int)(preg_replace('/^.*_(\d+)\.jpg$/i', "$1", basename($a)) ?? 0);
			$nb = (int)(preg_replace('/^.*_(\d+)\.jpg$/i', "$1", basename($b)) ?? 0);

			return $na <=> $nb;
		});
		foreach ($sidecar as $path) {
			$paths[] = $path;
		}

		return array_values(array_unique($paths));
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
