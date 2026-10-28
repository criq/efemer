<?php

namespace App\Classes\Ingest;

use App\Classes\Storage\StorageFile;
use Katu\Tools\Calendar\Time;

class IngestItemFile extends \Katu\Models\Model
{
	const TABLE = "ingest_item_files";

	public const SLOT_PRE_COPY = "pre_copy";
	public const SLOT_POST_COPY = "post_copy";
	public const SLOT_UNKNOWN = "unknown";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public int|string|null $ingestItemId = null;
	public int|string|null $storageFileId = null;
	public ?int $position = null;
	public ?string $caption = null;
	public ?string $keywords = null;
	public ?string $contentHash = null;
	public ?string $sourceSlot = null;
	public int|string|null $galleryIndex = null;

	public function setIngestItem(IngestItem $item): IngestItemFile
	{
		$this->ingestItemId = $item->getId();

		return $this;
	}

	public function setStorageFile(StorageFile $storageFile): IngestItemFile
	{
		$this->storageFileId = $storageFile->getId();

		return $this;
	}

	public function getStorageFile(): StorageFile
	{
		return StorageFile::get($this->storageFileId);
	}

	public function setPosition(int $position): IngestItemFile
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): ?int
	{
		return $this->position;
	}

	public function setCaption(?string $caption): IngestItemFile
	{
		$this->caption = $caption;

		return $this;
	}

	public function getCaption(): ?string
	{
		return $this->caption;
	}

	public function setContentHash(?string $contentHash): IngestItemFile
	{
		$this->contentHash = $contentHash;

		return $this;
	}

	public function getContentHash(): ?string
	{
		return $this->contentHash;
	}

	public function setKeywords(?array $keywords): IngestItemFile
	{
		$this->keywords = $keywords === null || $keywords === []
			? null
			: json_encode(array_values($keywords), JSON_UNESCAPED_UNICODE);

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getKeywords(): array
	{
		if ($this->keywords === null || $this->keywords === "") {
			return [];
		}

		$decoded = json_decode($this->keywords, true);

		return is_array($decoded) ? array_values(array_filter($decoded, "is_string")) : [];
	}

	public function setSourceSlot(?string $sourceSlot): IngestItemFile
	{
		$this->sourceSlot = $sourceSlot;

		return $this;
	}

	public function getSourceSlot(): ?string
	{
		return $this->sourceSlot;
	}

	public function getSourceSlotLabel(): string
	{
		return match ($this->getSourceSlot()) {
			self::SLOT_PRE_COPY => "před textem",
			self::SLOT_POST_COPY => "za textem",
			self::SLOT_UNKNOWN => "neznámé",
			default => $this->getSourceSlot() ?: "—",
		};
	}

	public function setGalleryIndex(?int $galleryIndex): IngestItemFile
	{
		$this->galleryIndex = $galleryIndex;

		return $this;
	}

	public function getGalleryIndex(): ?int
	{
		return $this->galleryIndex === null ? null : (int)$this->galleryIndex;
	}
}
