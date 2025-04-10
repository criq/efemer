<?php

namespace App\Classes\Posts;

use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;
use Katu\Types\TIdentifier;

class PostBlockFile extends \Katu\Models\Model
{
	const TABLE = "post_block_files";

	public $id;
	public $postBlockId;
	public $timeCreated;
	public $uri;

	public static function createFromUpload(PostBlock $postBlock, Upload $upload): PostBlockFile
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

		$object = new PostBlockFile;
		$object->setTimeCreated(new Time);
		$object->setPostBlock($postBlock);
		$object->setURI($storageObject->gcsUri());
		$object->persist();

		return $object;
	}

	public function setTimeCreated(Time $time): PostBlockFile
	{
		$this->timeCreated = $time;

		return $this;
	}

	public function setPostBlock(PostBlock $postBlock): PostBlockFile
	{
		$this->postBlockId = $postBlock->getId();

		return $this;
	}

	public function setURI(string $uri): PostBlockFile
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

	public function getImage(): \Katu\Tools\Images\Image
	{
		return new \Katu\Tools\Images\Image($this->getFile());
	}
}
