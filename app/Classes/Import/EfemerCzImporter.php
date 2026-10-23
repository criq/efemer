<?php

namespace App\Classes\Import;

use App\Classes\Pages\Components\Kinds\ImageGalleryKind;
use App\Classes\Pages\Components\Kinds\ImageKind;
use App\Classes\Pages\Components\Kinds\PageGalleryKind;
use App\Classes\Pages\Components\Kinds\TextKind;
use App\Classes\Pages\GalleryProperties;
use App\Classes\Pages\Page;
use App\Classes\Pages\PageComponent;
use App\Classes\Pages\PageComponentPageCollection;
use App\Classes\Pages\PageComponentStorageFileCollection;
use App\Classes\Storage\StorageFile;
use App\Classes\Tools\Slugger;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Katu\Tools\Calendar\Time;

class EfemerCzImporter
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
		$this->log("Fetching homepage…");
		$homeHtml = $this->fetch(self::BASE_URL);
		$homeDoc = $this->parseHtml($homeHtml);
		$homeXpath = new DOMXPath($homeDoc);

		$posts = $this->extractPostIndex($homeXpath);
		$this->log(sprintf("Found %d diary posts.", count($posts)));

		$this->wipeExistingContent();

		$diaryPages = [];
		foreach ($posts as $index => $post) {
			$this->log(sprintf("[%d/%d] %s", $index + 1, count($posts), $post["title"]));
			$diaryPages[] = $this->importPost($post);
		}

		$this->importHomepage($homeXpath, $diaryPages);
		$this->log("Import complete.");
	}

	private function wipeExistingContent(): void
	{
		$this->log("Clearing existing pages…");

		foreach (Page::getAll() as $page) {
			foreach ($page->getPageComponents() as $pageComponent) {
				if ($pageComponent->isRoot()) {
					$pageComponent->delete();
				}
			}
			$page->delete();
		}
	}

	/**
	 * @param list<array{title: string, href: string, date: ?string, thumbnail: ?string}> $posts
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
	 * @param array{title: string, href: string, date: ?string, thumbnail: ?string} $post
	 */
	private function importPost(array $post): Page
	{
		$url = $this->absoluteUrl($post["href"]);
		$html = $this->fetch($url);
		$doc = $this->parseHtml($html);
		$xpath = new DOMXPath($doc);

		$path = $this->pathFromHref($post["href"]);
		$time = $this->parseCzechDate($post["date"]) ?: new Time;

		$page = new Page;
		$page->setTimeCreated($time);
		$page->setTitle($post["title"]);
		$page->setPath($path);
		$page->persist();

		$position = 1;
		$images = $this->extractOrderedImages($xpath);
		$textHtml = $this->extractCopyHtml($xpath);
		$perexHtml = $this->extractPerexHtml($xpath);

		$titleText = $this->createComponent($page, new TextKind, $position++, null);
		$titleText->setValue(
			"<h1>" . htmlspecialchars((string)$post["title"], ENT_QUOTES | ENT_HTML5, "UTF-8") . "</h1>",
		)->persist();

		if (count($images) > 0) {
			$hero = array_shift($images);
			$heroComponent = $this->createComponent($page, new ImageKind, $position++, null);
			$this->attachRemoteImage($heroComponent, $hero, 1);
		}

		$body = trim(($perexHtml ?: "") . ($textHtml ?: ""));
		if ($body !== "") {
			$textComponent = $this->createComponent($page, new TextKind, $position++, null);
			$textComponent->setValue($body)->persist();
		}

		if (count($images) > 0) {
			$gallery = $this->createComponent($page, new ImageGalleryKind, $position++, null);
			$gallery->setTemplateCode("NATURAL");
			$gallery->setGalleryProperties(GalleryProperties::createDefault());
			$gallery->persist();

			$filePosition = 1;
			foreach ($images as $imageUrl) {
				$this->attachRemoteImage($gallery, $imageUrl, $filePosition++);
			}
		}

		return $page;
	}

	/**
	 * @param list<Page> $diaryPages
	 */
	private function importHomepage(DOMXPath $xpath, array $diaryPages): void
	{
		$this->log("Building homepage…");

		$page = new Page;
		$page->setTimeCreated(new Time);
		$page->setTitle("Efemér");
		$page->setPath(Slugger::HOMEPAGE_PATH);
		$page->persist();

		$position = 1;

		$hero = $this->createComponent($page, new TextKind, $position++, null);
		$hero->setValue(
			"<p>ekologické květinářství</p>\n"
			. "<h1>Efemér</h1>\n"
			. "<p>Lokální, přirozený a ohleduplný přístup ke kytici — podle místa, člověka a ročního období.</p>",
		)->persist();

		$sections = [
			["fragment-principy", "principy", "Principy"],
			["fragment-o-me", "o-me", "Já"],
			["fragment-nabizime", "nabizime", "Pro vás"],
		];

		foreach ($sections as [$fragmentId, $anchorId, $heading]) {
			$html = $this->rewriteLocalStaticUrls($this->extractSectionHtml($xpath, $fragmentId));
			if ($html === "") {
				continue;
			}

			$component = $this->createComponent($page, new TextKind, $position++, null);
			$component->setValue(
				"<h2 id=\"" . htmlspecialchars($anchorId, ENT_QUOTES | ENT_HTML5, "UTF-8") . "\">"
				. htmlspecialchars($heading, ENT_QUOTES | ENT_HTML5, "UTF-8")
				. "</h2>\n"
				. $html,
			)->persist();
		}

		$denikHeading = $this->createComponent($page, new TextKind, $position++, null);
		$denikHeading->setValue("<h2 id=\"denik\">Deník</h2>")->persist();

		$gallery = $this->createComponent($page, new PageGalleryKind, $position++, null);
		$gallery->setGalleryProperties(GalleryProperties::fromArray([
			"columnsMin" => 1,
			"columnsMax" => 1,
		]));
		$gallery->persist();

		$entries = [];
		foreach ($diaryPages as $diaryPage) {
			$entries[] = [
				"pageId" => (int)$diaryPage->getId(),
				"template" => "IMAGE_AND_TEXT",
			];
		}
		PageComponentPageCollection::syncFromPageEntries($gallery, $entries);
	}

	private function createComponent(Page $page, $kind, int $position, ?PageComponent $parent): PageComponent
	{
		$component = new PageComponent;
		$component->setTimeCreated(new Time);
		$component->setPage($page);
		$component->setParent($parent);
		$component->setKind($kind);
		$component->setPosition($position);
		$component->setTemplateCode($kind::getTemplates()->getDefaultCode());
		$component->persist();

		return $component;
	}

	private function attachRemoteImage(PageComponent $pageComponent, string $sourceUrl, int $position): void
	{
		$storageFile = $this->storageFileFromUrl($sourceUrl);
		if (!$storageFile) {
			$this->log("  skip image (download failed): " . $sourceUrl);

			return;
		}

		PageComponentStorageFileCollection::createFromStorageFile($pageComponent, $storageFile, $position);
	}

	private function storageFileFromUrl(string $sourceUrl): ?StorageFile
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

		$storageFile = StorageFile::createFromContents($contents, $fileName);
		$this->storageFilesBySourceUrl[$url] = $storageFile;

		return $storageFile;
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
			if ($src === "") {
				continue;
			}

			$urls[] = $this->absoluteUrl($src);
		}

		return array_values(array_unique($urls));
	}

	private function extractCopyHtml(DOMXPath $xpath): string
	{
		$section = $xpath->query("//div[@id=\"layout\"]//section[contains(@class,\"copy\")]")->item(0);
		if (!$section instanceof DOMElement) {
			return "";
		}

		return $this->innerHtml($section);
	}

	private function extractPerexHtml(DOMXPath $xpath): string
	{
		$perex = $xpath->query("//div[@id=\"layout\"]//div[contains(@class,\"perex\")]")->item(0);
		if (!$perex instanceof DOMElement) {
			return "";
		}

		return $this->innerHtml($perex);
	}

	private function extractSectionHtml(DOMXPath $xpath, string $fragmentId): string
	{
		$heading = $xpath->query("//*[@id=\"{$fragmentId}\"]")->item(0);
		if (!$heading) {
			return "";
		}

		$html = "";
		for ($node = $heading->nextSibling; $node; $node = $node->nextSibling) {
			if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ["h2", "h1"], true)) {
				break;
			}

			if ($node instanceof DOMElement && strtolower($node->tagName) === "section") {
				$html .= $this->innerHtml($node);
			}
		}

		return trim($html);
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

	private function pathFromHref(string $href): string
	{
		$path = preg_replace("/\.html?$/i", "", $href) ?? $href;
		$path = preg_replace("#^\./#", "", $path) ?? $path;

		return Slugger::slugifyPagePath($path);
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

	private function rewriteLocalStaticUrls(string $html): string
	{
		return preg_replace_callback(
			"#(<img\\b[^>]*\\bsrc=[\"'])([^\"']+)([\"'])#i",
			function (array $match): string {
				$src = $match[2];
				$absolute = $this->absoluteUrl($src);
				$path = parse_url($absolute, PHP_URL_PATH) ?: "";

				if (preg_match("#^/static/img/(.+)$#", $path, $parts)) {
					$localDir = dirname(__DIR__, 3) . "/public/img";
					if (!is_dir($localDir)) {
						mkdir($localDir, 0775, true);
					}

					$fileName = basename($parts[1]);
					$localPath = $localDir . "/" . $fileName;
					if (!is_file($localPath)) {
						$contents = $this->fetchBinary($absolute);
						if ($contents !== null && $contents !== "") {
							file_put_contents($localPath, $contents);
						}
					}

					if (is_file($localPath)) {
						$src = "/img/" . $fileName;
					}
				}

				return $match[1] . $src . $match[3];
			},
			$html,
		) ?? $html;
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
			"ssl" => [
				"verify_peer" => true,
				"verify_peer_name" => true,
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
			"ssl" => [
				"verify_peer" => true,
				"verify_peer_name" => true,
			],
		]);

		$contents = @file_get_contents($url, false, $context);
		if ($contents === false) {
			return null;
		}

		return $contents;
	}

	private function log(string $message): void
	{
		foreach ($this->loggers as $logger) {
			$logger($message);
		}
	}
}
