<?php

namespace App\Classes\Import;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Storage\StorageFile;
use Katu\Tools\Calendar\Time;

class InstagramImporter
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
		$root = $root ?: dirname(__DIR__, 3) . "/data/instagram";
		$classifier = new InstagramClassifier;
		$imported = 0;

		foreach (["efemer_flowers", "himmlhaus"] as $account) {
			$accountDir = $root . "/" . $account;
			if (!is_dir($accountDir)) {
				$this->log("Missing dump: " . $accountDir);
				continue;
			}

			foreach (glob($accountDir . "/*/post.json") ?: [] as $jsonPath) {
				$post = json_decode((string)file_get_contents($jsonPath), true);
				if (!is_array($post) || empty($post["shortcode"])) {
					continue;
				}

				if ($this->importOne($post, dirname($jsonPath), $classifier)) {
					$imported++;
				}
			}
		}

		$this->log("Imported {$imported} Instagram posts.");
	}

	public function importOne(array $data, string $postDir, ?InstagramClassifier $classifier = null): bool
	{
		$classifier = $classifier ?: new InstagramClassifier;

		return $this->importPost($data, $postDir, $classifier);
	}

	private function importPost(array $data, string $postDir, InstagramClassifier $classifier): bool
	{
		$account = (string)$data["account"];
		$shortcode = (string)$data["shortcode"];
		$existing = Post::getByInstagram($account, $shortcode);
		if ($existing) {
			return false;
		}

		$classified = $classifier->classify($data);
		$takenAt = !empty($data["taken_at"]) ? new Time((int)$data["taken_at"]) : new Time;
		$slugSource = $classified["title"] !== "Bez názvu"
			? $classified["title"]
			: $shortcode;
		$path = Post::pathFor($classified["section"], $takenAt->format("Y-m-d") . "-" . $slugSource);

		$post = new Post;
		$post->setTimeCreated($takenAt);
		$post->setPublishedAt($takenAt);
		$post->setTitle($classified["title"]);
		$post->setPath($path);
		$post->setSection($classified["section"]);
		$post->setExcerpt($classified["excerpt"]);
		$post->setBodyHtml($this->captionToHtml((string)($data["caption"] ?? "")));
		$post->setStatus($classified["status"]);
		$post->setSource(Post::SOURCE_INSTAGRAM);
		$post->setInstagramAccount($account);
		$post->setInstagramShortcode($shortcode);
		$post->setClassificationConfidence($classified["confidence"]);
		$post->persist();

		$position = 1;
		foreach ($data["media"] ?? [] as $media) {
			$local = $media["local_image"] ?? $media["local_video"] ?? null;
			if (!$local) {
				continue;
			}

			$filePath = $postDir . "/" . $local;
			if (!is_file($filePath)) {
				continue;
			}

			$contents = (string)file_get_contents($filePath);
			if ($contents === "") {
				continue;
			}

			$storageFile = StorageFile::createFromContents($contents, basename($filePath), $takenAt);
			$post->attachStorageFile($storageFile, $position++, null);
		}

		$this->log(sprintf(
			"%s/%s → %s [%s %.2f]",
			$account,
			$shortcode,
			Section::title($classified["section"]),
			$classified["status"],
			$classified["confidence"],
		));

		return true;
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
