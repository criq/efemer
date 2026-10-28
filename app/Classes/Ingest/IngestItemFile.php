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

	public const ROLE_LEAD = "lead";
	public const ROLE_SUPPORT = "support";
	public const ROLE_DETAIL = "detail";
	public const ROLE_CONTEXT = "context";
	public const ROLE_SKIP = "skip";

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
	public int|float|string|null $rating = null;
	public int|string|null $isCover = null;
	public ?string $role = null;
	public ?string $roleRationale = null;
	public int|string|null $roleLocked = null;
	public ?string $altText = null;
	public ?string $orientation = null;
	public ?string $focalCrop = null;
	public int|string|null $demote = null;

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

	public function setRating(?float $rating): IngestItemFile
	{
		$this->rating = $rating;

		return $this;
	}

	public function getRating(): ?float
	{
		return $this->rating === null || $this->rating === "" ? null : (float)$this->rating;
	}

	public function setIsCover(bool $isCover): IngestItemFile
	{
		$this->isCover = $isCover ? 1 : 0;

		return $this;
	}

	public function isCover(): bool
	{
		return (bool)(int)$this->isCover;
	}

	public function setRole(?string $role): IngestItemFile
	{
		$this->role = $role;

		return $this;
	}

	public function getRole(): ?string
	{
		return $this->role;
	}

	public function getRoleLabel(): string
	{
		return match ($this->getRole()) {
			self::ROLE_LEAD => "lead",
			self::ROLE_SUPPORT => "support",
			self::ROLE_DETAIL => "detail",
			self::ROLE_CONTEXT => "context",
			self::ROLE_SKIP => "skip",
			default => $this->getRole() ?: "—",
		};
	}

	public function setRoleRationale(?string $roleRationale): IngestItemFile
	{
		$this->roleRationale = $roleRationale;

		return $this;
	}

	public function getRoleRationale(): ?string
	{
		return $this->roleRationale;
	}

	public function setRoleLocked(bool $locked): IngestItemFile
	{
		$this->roleLocked = $locked ? 1 : 0;

		return $this;
	}

	public function isRoleLocked(): bool
	{
		return (bool)(int)$this->roleLocked;
	}

	public function setAltText(?string $altText): IngestItemFile
	{
		$this->altText = $altText;

		return $this;
	}

	public function getAltText(): ?string
	{
		return $this->altText;
	}

	public function setOrientation(?string $orientation): IngestItemFile
	{
		$this->orientation = $orientation;

		return $this;
	}

	public function getOrientation(): ?string
	{
		return $this->orientation;
	}

	public function setFocalCrop(?array $focalCrop): IngestItemFile
	{
		$this->focalCrop = $focalCrop === null
			? null
			: json_encode($focalCrop, JSON_UNESCAPED_UNICODE);

		return $this;
	}

	public function getFocalCrop(): ?array
	{
		if ($this->focalCrop === null || $this->focalCrop === "") {
			return null;
		}

		$decoded = json_decode($this->focalCrop, true);

		return is_array($decoded) ? $decoded : null;
	}

	public function setDemote(bool $demote): IngestItemFile
	{
		$this->demote = $demote ? 1 : 0;

		return $this;
	}

	public function isDemoted(): bool
	{
		return (bool)(int)$this->demote;
	}

	public function clearEnrichment(bool $keepLocks = true): IngestItemFile
	{
		$keepRole = $keepLocks && $this->isRoleLocked();
		if (!$keepRole) {
			$this->role = null;
			$this->roleRationale = null;
		}
		$this->keywords = null;
		$this->rating = null;
		$this->isCover = 0;
		$this->altText = null;
		$this->orientation = null;
		$this->focalCrop = null;
		$this->demote = 0;

		return $this;
	}
}
