<?php

namespace App\Classes\Pages;

use App\Classes\Storage\StorageFile;

class PageComponentStorageFile extends \Katu\Models\Model
{
	const TABLE = "page_components_storage_files";

	public int|string|null $id = null;
	public int|string|null $pageComponentId = null;
	public int|string|null $storageFileId = null;
	public ?int $position = null;
	public ?string $caption = null;

	public function setPageComponent(PageComponent $pageComponent): PageComponentStorageFile
	{
		$this->pageComponentId = $pageComponent->getId();

		return $this;
	}

	public function getPageComponent(): PageComponent
	{
		return PageComponent::get($this->pageComponentId);
	}

	public function setStorageFile(StorageFile $storageFile): PageComponentStorageFile
	{
		$this->storageFileId = $storageFile->getId();

		return $this;
	}

	public function getStorageFile(): StorageFile
	{
		return StorageFile::get($this->storageFileId);
	}

	public function setPosition(int $position): PageComponentStorageFile
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): ?int
	{
		return $this->position;
	}

	public function setCaption(?string $caption): PageComponentStorageFile
	{
		$this->caption = $caption;

		return $this;
	}

	public function getCaption(): ?string
	{
		return $this->caption;
	}

	public function getURI(): string
	{
		return $this->getStorageFile()->getURI();
	}

	public function getFileName(): string
	{
		return $this->getStorageFile()->getFileName();
	}

	public function getFile(): ?\Katu\Files\File
	{
		return $this->getStorageFile()->getFile();
	}

	public function getImage(): ?\Katu\Tools\Images\Image
	{
		return $this->getStorageFile()->getImage();
	}

	public function getAdminPayload(): array
	{
		$image = $this->getImage();

		return [
			"id" => $this->getId(),
			"fileName" => $this->getFileName(),
			"thumbnailUrl" => $image ? (string)$image->getImageVersion("THUMBNAIL")->getURL() : null,
			"position" => $this->getPosition(),
			"caption" => $this->getCaption(),
		];
	}

	public function delete(): bool
	{
		$storageFileId = $this->storageFileId;

		$deleted = parent::delete();

		$remaining = static::getBy([
			"storageFileId" => $storageFileId,
		])->getItems();

		if (count($remaining) === 0) {
			$storageFile = StorageFile::get($storageFileId);
			if ($storageFile) {
				$storageFile->delete();
			}
		}

		return $deleted;
	}
}
