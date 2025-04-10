<?php

namespace App\Classes\Posts;

use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;

class PostBlockFile extends \Katu\Models\Model
{
	const TABLE = "post_block_files";

	public $id;
	public $path;
	public $postBlockId;
	public $timeCreated;

	public static function createFromUpload(PostBlock $postBlock, Upload $upload): PostBlockFile
	{
		$path = implode("/", [
			\Katu\Tools\Random\Generator::getFileName(2),
			\Katu\Tools\Random\Generator::getFileName(2),
			\Katu\Tools\Random\Generator::getFileName(32),
			$upload->getFileName(),
		]);

		$file = new \Katu\Files\File(
			\App\App::getFileDir(),
			$path,
		);

		$file->set($upload->getStream()->getContents());

		$object = new PostBlockFile;
		$object->setTimeCreated(new Time);
		$object->setPostBlock($postBlock);
		$object->setPath($path);
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

	public function setPath(string $path): PostBlockFile
	{
		$this->path = $path;

		return $this;
	}
}
