<?php

namespace App\Classes\Posts;

use Katu\Files\Upload;
use Katu\Files\UploadCollection;

class PostBlockFileCollection extends \ArrayObject
{
	public static function createFromUploads(PostBlock $postBlock, UploadCollection $uploads): PostBlockFileCollection
	{
		return new PostBlockFileCollection(array_map(function (Upload $upload) use ($postBlock) {
			return PostBlockFile::createFromUpload($postBlock, $upload);
		}, $uploads->getArrayCopy()));
	}

	public function getSlice(int $length): PostBlockFileCollection
	{
		return new PostBlockFileCollection(array_slice($this->getArrayCopy(), 0, $length));
	}

	public function getFirst(): ?PostBlockFile
	{
		return array_values($this->getArrayCopy())[0] ?? null;
	}
}
