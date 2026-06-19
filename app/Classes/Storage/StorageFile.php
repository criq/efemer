<?php

namespace App\Classes\Storage;

use App\Classes\Pages\PageComponent;
use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;
use Katu\Types\TIdentifier;

class StorageFile extends \Katu\Models\Model
{
	const TABLE = "storage_files";

	public int|string|null $id = null;
	public int|string|null $pageComponentId = null;
	public Time|string|null $timeCreated = null;
	public ?string $uri = null;
	public ?int $position = null;
	public ?string $caption = null;

	public static function createFromUpload(PageComponent $pageComponent, Upload $upload, int $position): StorageFile
	{
		$time = new Time;

		$path = implode("/", [
			$time->format("Y"),
			$time->format("m"),
			$time->format("d"),
			\Katu\Tools\Random\Generator::getIdString(2),
			\Katu\Tools\Random\Generator::getIdString(16),
			$upload->getFileName(),
		]);

		$storageObject = \App\Classes\ThirdParty\Google\Cloud::getFilesBucket()->upload($upload->getStream()->getContents(), [
			"name" => $path,
		]);

		$object = new StorageFile;
		$object->setTimeCreated(new Time);
		$object->setPageComponent($pageComponent);
		$object->setPosition($position);
		$object->setURI($storageObject->gcsUri());
		$object->persist();

		return $object;
	}

	public function setTimeCreated(Time $time): StorageFile
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPageComponent(PageComponent $pageComponent): StorageFile
	{
		$this->pageComponentId = $pageComponent->getId();

		return $this;
	}

	public function setURI(string $uri): StorageFile
	{
		$this->uri = $uri;

		return $this;
	}

	public function setPosition(int $position): StorageFile
	{
		$this->position = $position;

		return $this;
	}

	public function getPosition(): ?int
	{
		return $this->position;
	}

	public function setCaption(?string $caption): StorageFile
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
		return $this->uri;
	}

	public function getPageComponent(): PageComponent
	{
		return PageComponent::get($this->pageComponentId);
	}

	public function getFileName(): string
	{
		return basename(parse_url($this->getURI(), PHP_URL_PATH) ?: $this->getURI());
	}

	public function getFile(): ?\Katu\Files\File
	{
		if (preg_match("/^gs:\/\/(?<bucket>.+)\/(?<name>.+)$/U", $this->getURI(), $match)) {
			$file = new \Katu\Files\File(\App\App::getTemporaryDir(), ...(new TIdentifier($this->getURI()))->getPathParts());
			if (!$file->exists()) {
				$client = \App\Classes\ThirdParty\Google\Cloud::getStorageClient();
				$bucket = $client->bucket($match["bucket"]);
				$object = $bucket->object($match["name"]);
				$file->set($object->downloadAsString());
			}

			return $file;
		}

		return null;
	}

	public function getImage(): ?\Katu\Tools\Images\Image
	{
		$file = $this->getFile();
		if (!$file) {
			return null;
		}

		return new \Katu\Tools\Images\Image($file);
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
}
