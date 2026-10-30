<?php

namespace App\Classes\Storage;

use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;
use Katu\Types\TIdentifier;

class StorageFile extends \Katu\Models\Model
{
	const TABLE = "storage_files";

	public int|string|null $id = null;
	public Time|string|null $timeCreated = null;
	public ?string $uri = null;
	public ?string $contentHash = null;

	public static function createFromUpload(Upload $upload): StorageFile
	{
		return static::createFromContents(
			$upload->getStream()->getContents(),
			$upload->getFileName(),
		);
	}

	public static function createFromContents(string $contents, string $fileName, ?Time $time = null): StorageFile
	{
		$time = $time ?: new Time;
		$hash = hash("sha256", $contents);
		$existing = static::getByContentHash($hash);
		if ($existing && \App\Classes\ThirdParty\Google\Cloud::uriBelongsToCurrentEnvironment($existing->getURI())) {
			return $existing;
		}

		$path = \App\Classes\ThirdParty\Google\Cloud::getFilesObjectPrefix() . implode("/", [
			$time->format("Y"),
			$time->format("m"),
			$time->format("d"),
			\Katu\Tools\Random\Generator::getIdString(2),
			\Katu\Tools\Random\Generator::getIdString(16),
			$fileName,
		]);

		$storageObject = \App\Classes\ThirdParty\Google\Cloud::getFilesBucket()->upload($contents, [
			"name" => $path,
		]);

		$object = new StorageFile;
		$object->setTimeCreated($time);
		$object->setURI($storageObject->gcsUri());
		$object->setContentHash($hash);
		$object->persist();

		return $object;
	}

	public static function getByContentHash(string $hash): ?StorageFile
	{
		if ($hash === "") {
			return null;
		}

		return static::getOneBy(["contentHash" => $hash]);
	}

	public function setContentHash(?string $contentHash): StorageFile
	{
		$this->contentHash = $contentHash;

		return $this;
	}

	public function getContentHash(): ?string
	{
		return $this->contentHash;
	}

	public function setTimeCreated(Time $time): StorageFile
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setURI(string $uri): StorageFile
	{
		$this->uri = $uri;

		return $this;
	}

	public function getURI(): string
	{
		return $this->uri;
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
		if (!$this->getURI()) {
			return null;
		}

		// Package by storage file id / gs URI so listing pages can build image
		// URLs without downloading every object from GCS first.
		return new \Katu\Tools\Images\Image(new StorageFileImageSource($this));
	}
}
