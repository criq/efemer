<?php

namespace App\Classes\Pages;

use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;
use Katu\Types\TIdentifier;

class PageComponentFile extends \Katu\Models\Model
{
	const TABLE = "page_component_files";

	public int|string|null $id = null;
	public int|string|null $pageComponentId = null;
	public Time|string|null $timeCreated = null;
	public ?string $uri = null;

	public static function createFromUpload(PageComponent $pageComponent, Upload $upload): PageComponentFile
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

		$object = new PageComponentFile;
		$object->setTimeCreated(new Time);
		$object->setPageComponent($pageComponent);
		$object->setURI($storageObject->gcsUri());
		$object->persist();

		return $object;
	}

	public function setTimeCreated(Time $time): PageComponentFile
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPageComponent(PageComponent $pageComponent): PageComponentFile
	{
		$this->pageComponentId = $pageComponent->getId();

		return $this;
	}

	public function setURI(string $uri): PageComponentFile
	{
		$this->uri = $uri;

		return $this;
	}

	public function getURI(): string
	{
		return $this->uri;
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
}
