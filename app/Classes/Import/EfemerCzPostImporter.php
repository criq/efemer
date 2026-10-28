<?php

namespace App\Classes\Import;

use App\Classes\Content\Post;
use App\Classes\Content\Section;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Storage\StorageFile;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Katu\Tools\Calendar\Time;

class EfemerCzPostImporter
{
	private const BASE_URL = "https://www.efemer.cz/";

	/** @var array<string, StorageFile> */
	private array $storageFilesBySourceUrl = [];

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function run(): void
	{
		$classifier = new InstagramClassifier;
		$html = $this->fetch(self::BASE_URL);
		$posts = $this->extractPostIndex(new DOMXPath($this->parseHtml($html)));
		$this->log(ConsoleStyle::step("Found " . count($posts) . " diary posts."));

		$imported = 0;
		foreach ($posts as $index => $post) {
			$title = $post["title"] ?: ("Záznam " . ($index + 1));
			$this->log(ConsoleStyle::item($index + 1, count($posts), $title));
			if ($this->importPost($post, $classifier)) {
				$imported++;
			}
		}

		$this->log(ConsoleStyle::done("posts from efemer.cz", $imported));
	}

	/**
	 * @param array{title: string, href: string, date: ?string, thumbnail: ?string} $post
	 */
	private function importPost(array $post, InstagramClassifier $classifier): bool
	{
		$url = $this->absoluteUrl($post["href"]);
		$html = $this->fetch($url);
		$doc = $this->parseHtml($html);
		$xpath = new DOMXPath($doc);
		$time = $this->parseCzechDate($post["date"]) ?: new Time;
		$title = trim((string)$post["title"]);
		if ($title === "") {
			$title = "Záznam";
		}

		$textHtml = trim(($this->extractPerexHtml($xpath) ?: "") . ($this->extractCopyHtml($xpath) ?: ""));
		$classified = $classifier->classify([
			"account" => "efemer_flowers",
			"caption" => $title . "\n" . strip_tags($textHtml),
			"taken_at" => $time->format("U"),
		]);

		$path = Post::pathFor($classified["section"], $time->format("Y-m-d") . "-" . $title);
		$existing = Post::getByPath($path);
		if ($existing) {
			return false;
		}

		$object = new Post;
		$object->setTimeCreated($time);
		$object->setPublishedAt($time);
		$object->setTitle($title);
		$object->setPath($path);
		$object->setSection($classified["section"]);
		$object->setExcerpt($classified["excerpt"]);
		$object->setBodyHtml($textHtml);
		$object->setStatus(Post::STATUS_PUBLISHED);
		$object->setSource(Post::SOURCE_EFEMER_CZ);
		$object->setClassificationConfidence($classified["confidence"]);
		$object->persist();

		$position = 1;
		foreach ($this->extractOrderedImages($xpath) as $imageUrl) {
			$storageFile = $this->storageFileFromUrl($imageUrl, $time);
			if (!$storageFile) {
				continue;
			}

			$object->attachStorageFile($storageFile, $position++);
		}

		$this->log(ConsoleStyle::muted("→") . " " . ConsoleStyle::title(Section::title($classified["section"])));

		return true;
	}

	private function storageFileFromUrl(string $sourceUrl, Time $time): ?StorageFile
	{
		$url = $this->absoluteUrl($sourceUrl);
		if (isset($this->storageFilesBySourceUrl[$url])) {
			return $this->storageFilesBySourceUrl[$url];
		}

		$contents = $this->fetchBinary($url);
		if ($contents === null || $contents === "") {
			return null;
		}

		$fileName = basename(parse_url($url, PHP_URL_PATH) ?: "image.jpg");
		if ($fileName === "" || $fileName === "/") {
			$fileName = "image.jpg";
		}

		$storageFile = StorageFile::createFromContents($contents, $fileName, $time);
		$this->storageFilesBySourceUrl[$url] = $storageFile;

		return $storageFile;
	}

	/**
	 * @return list<array{title: string, href: string, date: ?string, thumbnail: ?string}>
	 */
	private function extractPostIndex(DOMXPath $xpath): array
	{
		$posts = [];
		$seen = [];

		foreach ($xpath->query("//ul[contains(@class,\"posts-index\")]//li[contains(@class,\"post\")]") as $li) {
			if (!$li instanceof DOMElement) {
				continue;
			}

			$titleLink = $xpath->query(".//h3[contains(@class,\"title\")]/a", $li)->item(0);
			if (!$titleLink instanceof DOMElement) {
				continue;
			}

			$href = trim($titleLink->getAttribute("href"));
			if ($href === "" || isset($seen[$href])) {
				continue;
			}

			$seen[$href] = true;
			$dateNode = $xpath->query(".//span[contains(@class,\"date\")]", $li)->item(0);
			$thumb = $xpath->query(".//div[contains(@class,\"thumbnail\")]//img", $li)->item(0);

			$posts[] = [
				"title" => trim(html_entity_decode($titleLink->textContent, ENT_QUOTES | ENT_HTML5, "UTF-8")),
				"href" => $href,
				"date" => $dateNode ? trim($dateNode->textContent) : null,
				"thumbnail" => $thumb instanceof DOMElement ? $thumb->getAttribute("src") : null,
			];
		}

		return $posts;
	}

	/**
	 * @return list<string>
	 */
	private function extractOrderedImages(DOMXPath $xpath): array
	{
		$urls = [];
		foreach ($xpath->query("//div[@id=\"layout\"]//ul[contains(@class,\"images\")]//img") as $img) {
			if (!$img instanceof DOMElement) {
				continue;
			}

			$src = trim($img->getAttribute("src"));
			if ($src !== "") {
				$urls[] = $this->absoluteUrl($src);
			}
		}

		return array_values(array_unique($urls));
	}

	private function extractCopyHtml(DOMXPath $xpath): string
	{
		$section = $xpath->query("//div[@id=\"layout\"]//section[contains(@class,\"copy\")]")->item(0);

		return $section instanceof DOMElement ? $this->innerHtml($section) : "";
	}

	private function extractPerexHtml(DOMXPath $xpath): string
	{
		$perex = $xpath->query("//div[@id=\"layout\"]//div[contains(@class,\"perex\")]")->item(0);

		return $perex instanceof DOMElement ? $this->innerHtml($perex) : "";
	}

	private function innerHtml(DOMElement $element): string
	{
		$html = "";
		foreach ($element->childNodes as $child) {
			$html .= $element->ownerDocument->saveHTML($child);
		}

		$html = preg_replace("/<!--.*?-->/s", "", $html) ?? $html;
		$html = preg_replace("/\s+/u", " ", $html) ?? $html;

		return trim($html);
	}

	private function parseCzechDate(?string $date): ?Time
	{
		if ($date === null || trim($date) === "") {
			return null;
		}

		if (!preg_match("/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/", trim($date), $match)) {
			return null;
		}

		return new Time(sprintf("%04d-%02d-%02d 12:00:00", (int)$match[3], (int)$match[2], (int)$match[1]));
	}

	private function absoluteUrl(string $href): string
	{
		$href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, "UTF-8");
		if (preg_match("#^https?://#i", $href)) {
			return $href;
		}

		$href = preg_replace("#^\./#", "", $href) ?? $href;
		while (str_starts_with($href, "../")) {
			$href = substr($href, 3);
		}

		return rtrim(self::BASE_URL, "/") . "/" . ltrim($href, "/");
	}

	private function parseHtml(string $html): DOMDocument
	{
		$document = new DOMDocument;
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML("<?xml encoding=\"UTF-8\">" . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $document;
	}

	private function fetch(string $url): string
	{
		$context = stream_context_create([
			"http" => [
				"header" => "User-Agent: EfemerImporter/1.0\r\nAccept: text/html\r\n",
				"timeout" => 60,
				"follow_location" => 1,
			],
		]);
		$contents = @file_get_contents($url, false, $context);
		if ($contents === false) {
			throw new \RuntimeException("Failed to fetch " . $url);
		}

		return $contents;
	}

	private function fetchBinary(string $url): ?string
	{
		$context = stream_context_create([
			"http" => [
				"header" => "User-Agent: EfemerImporter/1.0\r\nAccept: image/*\r\n",
				"timeout" => 60,
				"follow_location" => 1,
			],
		]);
		$contents = @file_get_contents($url, false, $context);

		return $contents === false ? null : $contents;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
