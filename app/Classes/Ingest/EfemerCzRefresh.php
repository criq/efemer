<?php

namespace App\Classes\Ingest;

use App\Classes\Jobs\ConsoleStyle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Katu\Tools\Calendar\Time;

class EfemerCzRefresh
{
	private const BASE_URL = "https://www.efemer.cz/";

	/** @var list<callable(string): void> */
	private array $loggers = [];

	public function onLog(callable $logger): self
	{
		$this->loggers[] = $logger;

		return $this;
	}

	public function run(): void
	{
		$loader = (new Loader)->onLog(fn (string $message) => $this->log($message));
		$html = $this->fetch(self::BASE_URL);
		$posts = $this->extractPostIndex(new DOMXPath($this->parseHtml($html)));
		$this->log(ConsoleStyle::step("Found " . count($posts) . " diary posts on efemer.cz."));

		$loaded = 0;
		foreach ($posts as $index => $post) {
			$title = $post["title"] !== "" ? $post["title"] : ("Záznam " . ($index + 1));
			$this->log(ConsoleStyle::item($index + 1, count($posts), $title));
			try {
				if ($this->loadPost($loader, $post, $title)) {
					$loaded++;
				}
			} catch (\Throwable $exception) {
				$this->log(ConsoleStyle::warn($exception->getMessage()));
			}
		}

		$this->log(ConsoleStyle::done("Efemer.cz refresh", $loaded));
	}

	/**
	 * @param array{title: string, href: string, date: ?string, thumbnail: ?string} $post
	 */
	private function loadPost(Loader $loader, array $post, string $title): bool
	{
		$url = $this->absoluteUrl($post["href"]);
		$sourceKey = parse_url($url, PHP_URL_PATH) ?: $post["href"];
		$sourceKey = "/" . ltrim((string)$sourceKey, "/");

		$flags = [];
		try {
			$html = $this->fetch($url);
		} catch (\Throwable $exception) {
			$loader->upsert(
				IngestItem::SOURCE_EFEMER_CZ,
				$sourceKey,
				$url,
				$title,
				BodyDates::parseIndexDate($post["date"]),
				null,
				[],
				null,
				null,
				["fetch_failed"],
			);

			throw $exception;
		}

		$xpath = new DOMXPath($this->parseHtml($html));
		$textHtml = trim(($this->extractPerexHtml($xpath) ?: "") . ($this->extractCopyHtml($xpath) ?: ""));
		if ($textHtml === "") {
			$flags[] = "thin_text";
		}

		$postedAt = BodyDates::firstFromHtml($textHtml)
			?: BodyDates::parseIndexDate($post["date"]);
		if ($postedAt === null) {
			$flags[] = "no_date";
			$postedAt = new Time;
		}

		$files = [];
		foreach ($this->extractOrderedImages($xpath) as $imageUrl) {
			$contents = $this->fetchBinary($imageUrl);
			if ($contents === null || $contents === "") {
				continue;
			}

			$fileName = basename(parse_url($imageUrl, PHP_URL_PATH) ?: "image.jpg");
			if ($fileName === "" || $fileName === "/") {
				$fileName = "image.jpg";
			}

			$files[] = [
				"contents" => $contents,
				"fileName" => $fileName,
			];
		}

		$before = IngestItem::getBySourceKey(IngestItem::SOURCE_EFEMER_CZ, $sourceKey);
		$beforeHash = $before?->getContentHash();
		$item = $loader->upsert(
			IngestItem::SOURCE_EFEMER_CZ,
			$sourceKey,
			$url,
			$title,
			$postedAt,
			$textHtml !== "" ? $textHtml : null,
			$files,
			null,
			null,
			$flags,
		);

		return $beforeHash !== $item->getContentHash();
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
				"header" => "User-Agent: EfemerIngest/1.0\r\nAccept: text/html\r\n",
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
				"header" => "User-Agent: EfemerIngest/1.0\r\nAccept: image/*\r\n",
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
