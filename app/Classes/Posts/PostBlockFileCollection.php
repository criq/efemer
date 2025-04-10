<?php

namespace App\Classes\Posts;

use Katu\Files\Upload;
use Katu\Files\UploadCollection;

class PostBlockFileCollection extends \ArrayObject
{
	public static function createFromUploads(PostBlock $postBlock, UploadCollection $uploads)
	{
		return new PostBlockFileCollection(array_map(function (Upload $upload) use ($postBlock) {
			return PostBlockFile::createFromUpload($postBlock, $upload);
		}, $uploads->getArrayCopy()));
	}
}
