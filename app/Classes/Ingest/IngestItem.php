<?php

namespace App\Classes\Ingest;

use App\Classes\Content\Post;
use App\Classes\Storage\StorageFile;
use Katu\Tools\Calendar\Time;

class IngestItem extends \Katu\Models\Model
{
	const TABLE = "ingest_items";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public Time|string|null $timeUpdated = null;
	public ?string $source = null;
	public ?string $sourceKey = null;
	public ?string $sourceUrl = null;
	public ?string $instagramAccount = null;
	public ?string $instagramShortcode = null;
	public ?string $title = null;
	public Time|string|null $postedAt = null;
	public ?string $bodyHtml = null;
	public ?string $contentHash = null;
	public ?string $status = null;
	public ?string $kind = null;
	public ?string $keywords = null;
	public ?string $themes = null;
	public ?string $entities = null;
	public ?string $qualityFlags = null;
	public ?string $categories = null;
	public ?string $classificationRaw = null;
	public mixed $embedding = null;
	public int|string|null $duplicateOfId = null;
	public int|string|null $postId = null;
	public int|string|null $eventId = null;

	public const SOURCE_EFEMER_CZ = "efemer_cz";
	public const SOURCE_INSTAGRAM = "instagram";

	public const STATUS_LOADED = "loaded";
	public const STATUS_KEYWORDED = "keyworded";
	public const STATUS_CATEGORIZED = "categorized";
	public const STATUS_MATERIALIZED = "materialized";
	public const STATUS_SKIPPED = "skipped";

	/**
	 * Canonical source key: no leading slash (paths like 2017/08/08/post.html).
	 */
	public static function normalizeSourceKey(string $sourceKey): string
	{
		return ltrim($sourceKey, "/");
	}

	public static function getBySourceKey(string $source, string $sourceKey): ?IngestItem
	{
		$normalized = static::normalizeSourceKey($sourceKey);
		$item = static::getOneBy([
			"source" => $source,
			"sourceKey" => $normalized,
		]);
		if ($item) {
			return $item;
		}

		// Legacy rows stored with a leading slash before key normalization.
		return static::getOneBy([
			"source" => $source,
			"sourceKey" => "/" . $normalized,
		]);
	}

	/**
	 * @return list<string>
	 */
	public static function getInstagramShortcodes(string $account): array
	{
		$shortcodes = [];
		foreach (static::getBy(["instagramAccount" => $account])->getItems() as $item) {
			$code = $item->getInstagramShortcode();
			if ($code) {
				$shortcodes[] = $code;
			}
		}

		return $shortcodes;
	}

	public function setSource(string $source): IngestItem
	{
		$this->source = $source;

		return $this;
	}

	public function getSource(): string
	{
		return (string)$this->source;
	}

	public function setSourceKey(string $sourceKey): IngestItem
	{
		$this->sourceKey = $sourceKey;

		return $this;
	}

	public function getSourceKey(): string
	{
		return (string)$this->sourceKey;
	}

	public function setSourceUrl(?string $sourceUrl): IngestItem
	{
		$this->sourceUrl = $sourceUrl;

		return $this;
	}

	public function getSourceUrl(): ?string
	{
		return $this->sourceUrl;
	}

	public function setInstagramAccount(?string $account): IngestItem
	{
		$this->instagramAccount = $account;

		return $this;
	}

	public function getInstagramAccount(): ?string
	{
		return $this->instagramAccount;
	}

	public function setInstagramShortcode(?string $shortcode): IngestItem
	{
		$this->instagramShortcode = $shortcode;

		return $this;
	}

	public function getInstagramShortcode(): ?string
	{
		return $this->instagramShortcode;
	}

	public function setTitle(?string $title): IngestItem
	{
		$this->title = $title;

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setPostedAt(?Time $time): IngestItem
	{
		$this->postedAt = $time;

		return $this;
	}

	public function getPostedAt(): ?Time
	{
		if ($this->postedAt instanceof Time) {
			return $this->postedAt;
		}

		if (is_string($this->postedAt) && $this->postedAt !== "") {
			return new Time($this->postedAt);
		}

		return null;
	}

	public function setBodyHtml(?string $bodyHtml): IngestItem
	{
		$this->bodyHtml = $bodyHtml;

		return $this;
	}

	public function getBodyHtml(): ?string
	{
		return $this->bodyHtml;
	}

	public function setContentHash(?string $contentHash): IngestItem
	{
		$this->contentHash = $contentHash;

		return $this;
	}

	public function getContentHash(): ?string
	{
		return $this->contentHash;
	}

	public function setStatus(string $status): IngestItem
	{
		$this->status = $status;

		return $this;
	}

	public function getStatus(): string
	{
		return $this->status ?: self::STATUS_LOADED;
	}

	public function setQualityFlags(array $flags): IngestItem
	{
		$flags = array_values(array_unique(array_filter($flags, fn ($f) => is_string($f) && $f !== "")));
		$this->qualityFlags = $flags === [] ? null : json_encode($flags, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getQualityFlags(): array
	{
		if ($this->qualityFlags === null || $this->qualityFlags === "") {
			return [];
		}

		$decoded = json_decode($this->qualityFlags, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}

	public function clearEnrichment(): IngestItem
	{
		$this->kind = null;
		$this->keywords = null;
		$this->themes = null;
		$this->entities = null;
		$this->categories = null;
		$this->classificationRaw = null;
		$this->embedding = null;

		return $this;
	}

	public function touchUpdated(): IngestItem
	{
		$this->timeUpdated = new Time;

		return $this;
	}

	public function getFiles(): IngestItemFileCollection
	{
		$files = IngestItemFile::getBy([
			"ingestItemId" => $this->getId(),
		])->getItems();

		usort($files, function (IngestItemFile $a, IngestItemFile $b) {
			return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
		});

		return new IngestItemFileCollection($files);
	}

	public function clearFiles(): void
	{
		foreach ($this->getFiles() as $file) {
			$file->delete();
		}
	}

	public function attachStorageFile(
		StorageFile $storageFile,
		int $position,
		?string $caption = null,
		?string $contentHash = null,
		?string $sourceSlot = null,
		?int $galleryIndex = null,
	): IngestItemFile {
		$link = new IngestItemFile;
		$link->timeCreated = new Time;
		$link->setIngestItem($this);
		$link->setStorageFile($storageFile);
		$link->setPosition($position);
		$link->setCaption($caption);
		$link->setContentHash($contentHash ?: $storageFile->getContentHash());
		$link->setSourceSlot($sourceSlot);
		$link->setGalleryIndex($galleryIndex);
		$link->persist();

		return $link;
	}

	public function getPost(): ?Post
	{
		return $this->postId ? Post::get($this->postId) : null;
	}

	public function getSourceLabel(): string
	{
		return match ($this->getSource()) {
			self::SOURCE_EFEMER_CZ => "efemer.cz",
			self::SOURCE_INSTAGRAM => $this->getInstagramAccount()
				? "@" . $this->getInstagramAccount()
				: "Instagram",
			default => $this->getSource(),
		};
	}

	public function getStatusLabel(): string
	{
		return match ($this->getStatus()) {
			self::STATUS_LOADED => "Načteno",
			self::STATUS_KEYWORDED => "Klíčová slova",
			self::STATUS_CATEGORIZED => "Kategorie",
			self::STATUS_MATERIALIZED => "Zveřejněno do příspěvků",
			self::STATUS_SKIPPED => "Přeskočeno",
			default => $this->getStatus(),
		};
	}

	public function getHeroImage(): ?\Katu\Tools\Images\Image
	{
		$file = $this->getFiles()->getFirst();
		if (!$file) {
			return null;
		}

		try {
			return $file->getStorageFile()->getImage();
		} catch (\Throwable) {
			return null;
		}
	}

	public function getFileCount(): int
	{
		return count($this->getFiles());
	}

	/**
	 * @return list<string>
	 */
	public function getKeywordsList(): array
	{
		if ($this->keywords === null || $this->keywords === "") {
			return [];
		}

		$decoded = json_decode($this->keywords, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}

	/**
	 * @return list<string>
	 */
	public function getThemesList(): array
	{
		if ($this->themes === null || $this->themes === "") {
			return [];
		}

		$decoded = json_decode($this->themes, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}
}
