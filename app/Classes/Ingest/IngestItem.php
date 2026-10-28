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
	public int|string|null $coverFileId = null;
	public ?string $coverRationale = null;
	public int|string|null $coverLocked = null;
	public ?string $seriesKey = null;
	public ?string $palette = null;
	public ?string $layoutHints = null;
	public ?string $enrichmentVersion = null;
	public Time|string|null $enrichedAt = null;

	public const SOURCE_EFEMER_CZ = "efemer_cz";
	public const SOURCE_INSTAGRAM = "instagram";

	public const STATUS_LOADED = "loaded";
	public const STATUS_KEYWORDED = "keyworded";
	public const STATUS_CATEGORIZED = "categorized";
	public const STATUS_MATERIALIZED = "materialized";
	public const STATUS_SKIPPED = "skipped";

	public const ENRICHMENT_VERSION = "kw-v3";

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

	public function hasQualityFlag(string $flag): bool
	{
		return in_array($flag, $this->getQualityFlags(), true);
	}

	public function addQualityFlag(string $flag): IngestItem
	{
		$flag = trim($flag);
		if ($flag === "") {
			return $this;
		}

		$flags = $this->getQualityFlags();
		if (!in_array($flag, $flags, true)) {
			$flags[] = $flag;
			$this->setQualityFlags($flags);
		}

		return $this;
	}

	public function removeQualityFlag(string $flag): IngestItem
	{
		$flags = array_values(array_filter(
			$this->getQualityFlags(),
			fn (string $existing) => $existing !== $flag,
		));
		$this->setQualityFlags($flags);

		return $this;
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
		if (!(int)$this->coverLocked) {
			$this->coverFileId = null;
			$this->coverRationale = null;
		}
		$this->seriesKey = null;
		$this->palette = null;
		$this->layoutHints = null;
		$this->enrichmentVersion = null;
		$this->enrichedAt = null;

		return $this;
	}

	/**
	 * Wipe LLM / vision fields and return status to loaded (keeps stage-1 content + files).
	 * Respects coverLocked / roleLocked unless $forceLocks is true.
	 */
	public function resetEnrichment(bool $forceLocks = false): IngestItem
	{
		if ($forceLocks) {
			$this->coverLocked = 0;
		}

		$this->clearEnrichment();
		if ($forceLocks || !(int)$this->coverLocked) {
			$this->coverFileId = null;
			$this->coverRationale = null;
		}

		foreach ($this->getFiles() as $file) {
			if ($forceLocks) {
				$file->setRoleLocked(false);
			}
			$file->clearEnrichment(!$forceLocks);
			$file->persist();
		}

		if (in_array($this->getStatus(), [
			self::STATUS_KEYWORDED,
			self::STATUS_CATEGORIZED,
		], true)) {
			$this->setStatus(self::STATUS_LOADED);
		}

		$this->touchUpdated();
		$this->persist();

		return $this;
	}

	public function setKind(?string $kind): IngestItem
	{
		$this->kind = $kind;

		return $this;
	}

	public function getKind(): ?string
	{
		return $this->kind;
	}

	public function setKeywordsList(array $keywords): IngestItem
	{
		$keywords = array_values(array_unique(array_filter($keywords, fn ($k) => is_string($k) && $k !== "")));
		$this->keywords = $keywords === [] ? null : json_encode($keywords, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	public function setThemesList(array $themes): IngestItem
	{
		$themes = array_values(array_unique(array_filter($themes, fn ($t) => is_string($t) && $t !== "")));
		$this->themes = $themes === [] ? null : json_encode($themes, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	public function setEntitiesList(array $entities): IngestItem
	{
		$entities = array_values(array_unique(array_filter($entities, fn ($e) => is_string($e) && $e !== "")));
		$this->entities = $entities === [] ? null : json_encode($entities, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getEntitiesList(): array
	{
		if ($this->entities === null || $this->entities === "") {
			return [];
		}

		$decoded = json_decode($this->entities, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}

	public function setClassificationRaw(?string $raw): IngestItem
	{
		$this->classificationRaw = $raw;

		return $this;
	}

	public function getClassificationRaw(): ?string
	{
		return $this->classificationRaw;
	}

	public function setCoverFileId(?int $coverFileId): IngestItem
	{
		$this->coverFileId = $coverFileId;

		return $this;
	}

	public function getCoverFileId(): ?int
	{
		return $this->coverFileId !== null ? (int)$this->coverFileId : null;
	}

	public function setCoverRationale(?string $coverRationale): IngestItem
	{
		$this->coverRationale = $coverRationale;

		return $this;
	}

	public function getCoverRationale(): ?string
	{
		return $this->coverRationale;
	}

	public function setCoverLocked(bool $locked): IngestItem
	{
		$this->coverLocked = $locked ? 1 : 0;

		return $this;
	}

	public function isCoverLocked(): bool
	{
		return (bool)(int)$this->coverLocked;
	}

	public function setSeriesKey(?string $seriesKey): IngestItem
	{
		$this->seriesKey = $seriesKey;

		return $this;
	}

	public function getSeriesKey(): ?string
	{
		return $this->seriesKey;
	}

	public function setPalette(?array $palette): IngestItem
	{
		$this->palette = $palette === null || $palette === []
			? null
			: json_encode(array_values($palette), JSON_UNESCAPED_UNICODE);

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getPalette(): array
	{
		if ($this->palette === null || $this->palette === "") {
			return [];
		}

		$decoded = json_decode($this->palette, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}

	public function setLayoutHints(?array $hints): IngestItem
	{
		$this->layoutHints = $hints === null
			? null
			: json_encode($hints, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	public function getLayoutHints(): ?array
	{
		if ($this->layoutHints === null || $this->layoutHints === "") {
			return null;
		}

		$decoded = json_decode($this->layoutHints, true);

		return is_array($decoded) ? $decoded : null;
	}

	public function setEnrichmentVersion(?string $version): IngestItem
	{
		$this->enrichmentVersion = $version;

		return $this;
	}

	public function getEnrichmentVersion(): ?string
	{
		return $this->enrichmentVersion;
	}

	public function setEnrichedAt(?Time $time): IngestItem
	{
		$this->enrichedAt = $time;

		return $this;
	}

	public function getEnrichedAt(): ?Time
	{
		if ($this->enrichedAt instanceof Time) {
			return $this->enrichedAt;
		}

		if (is_string($this->enrichedAt) && $this->enrichedAt !== "") {
			return new Time($this->enrichedAt);
		}

		return null;
	}

	public function needsEnrichment(string $version = self::ENRICHMENT_VERSION): bool
	{
		if ($this->getStatus() !== self::STATUS_KEYWORDED) {
			return true;
		}

		return $this->getEnrichmentVersion() !== $version;
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

	public function getCoverFile(): ?IngestItemFile
	{
		$coverId = $this->getCoverFileId();
		if ($coverId) {
			foreach ($this->getFiles() as $file) {
				if ((int)$file->getId() === $coverId) {
					return $file;
				}
			}
		}

		foreach ($this->getFiles() as $file) {
			if ($file->isCover()) {
				return $file;
			}
		}

		return $this->getFiles()->getFirst();
	}

	public function getHeroImage(): ?\Katu\Tools\Images\Image
	{
		$file = $this->getCoverFile() ?: $this->getFiles()->getFirst();
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
