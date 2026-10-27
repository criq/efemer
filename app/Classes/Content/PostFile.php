<?php

namespace App\Classes\Content;

use App\Classes\Storage\StorageFile;

class PostFile extends \Katu\Models\Model
{
	const TABLE = "post_files";

	public int|string|null $id = null;
	public \Katu\Tools\Calendar\Time|string|null $timeCreated = null;
	public int|string|null $postId = null;
	public int|string|null $storageFileId = null;
	public ?int $position = null;
	public ?string $caption = null;

	public function setPost(Post $post): PostFile
	{
		$this->postId = $post->getId();

		return $this;
	}

	public function setStorageFile(StorageFile $storageFile): PostFile
	{
		$this->storageFileId = $storageFile->getId();

		return $this;
	}

	public function getStorageFile(): StorageFile
	{
		return StorageFile::get($this->storageFileId);
	}

	public function setPosition(int $position): PostFile
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): ?int
	{
		return $this->position;
	}

	public function setCaption(?string $caption): PostFile
	{
		$this->caption = $caption;

		return $this;
	}

	public function getCaption(): ?string
	{
		return $this->caption;
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
			"fileName" => $this->getStorageFile()->getFileName(),
			"thumbnailUrl" => $image ? (string)$image->getImageVersion("THUMBNAIL")->getURL() : null,
			"position" => $this->getPosition(),
			"caption" => $this->getCaption(),
		];
	}
}
